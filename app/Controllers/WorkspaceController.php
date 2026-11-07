<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\Database as DB;
use App\Services\Auth;
use App\Services\Shows;
use App\Services\Orders;
use App\Services\Planning;
use App\Services\Tables;
use App\Services\Validation;
use App\Services\HttpException;
use App\Middleware\Permissions;

final class WorkspaceController
{
    public function dashboard(): void
    {
        $widgets = [
            ['Active Shows',"SELECT COUNT(*) AS total FROM shows WHERE status NOT IN ('Completed','Archived')",'/shows','theater_comedy'],
            ['Pending Pulls',"SELECT COUNT(*) AS total FROM revisions r JOIN revision_approvals ap ON ap.revision_id=r.id WHERE ap.status='Approved' AND (SELECT COALESCE(SUM(quantity),0) FROM revision_lines l WHERE l.revision_id=r.id)>(SELECT COUNT(*) FROM assignments a JOIN revision_lines l ON l.id=a.line_id WHERE l.revision_id=r.id)",'/orders','inventory_2'],
            ['Pending Revisions',"SELECT COUNT(*) AS total FROM revisions r JOIN revision_approvals ap ON ap.revision_id=r.id WHERE ap.status='Pending' AND r.revision_number>0",'/orders','difference'],
            ['Returns Due',"SELECT COUNT(*) AS total FROM assignments WHERE ended_at IS NULL AND return_date<=CURRENT_DATE()",'/shows','assignment_return'],
            ['Open Repairs',"SELECT COUNT(*) AS total FROM repairs WHERE status<>'Completed'",'/repairs','build'],
            ['Missing Equipment',"SELECT COUNT(*) AS total FROM assets WHERE state='Missing'",'/inventory','warning'],
            ['Upcoming Shop Prep',"SELECT COUNT(*) AS total FROM shows WHERE prep_start BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(),INTERVAL 14 DAY) AND status NOT IN ('Completed','Archived')",'/shows','event'],
        ];
        foreach ($widgets as &$widget) {
            $widget[1] = (int)DB::one($widget[1])['total'];
        }
        unset($widget);
        $shortages = [];
        foreach (DB::all('SELECT id,name FROM item_types') as $type) {
            $availability = Planning::availability((int)$type['id'],gmdate('Y-m-d'),gmdate('Y-m-d',time()+30*86400));
            if ($availability['shortage']>0) {
                $shortages[] = ['name'=>$type['name'],...$availability];
            }
        }
        $widgets[] = ['Inventory Shortages',count($shortages),'/reports/timeline','error'];
        $activity = DB::all('SELECT a.action,a.created_at,u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 12');
        $upcoming = DB::all("SELECT id,name,theatre,prep_start,status FROM shows WHERE status NOT IN ('Completed','Archived') ORDER BY prep_start LIMIT 6");
        \view('dashboard',compact('widgets','shortages','activity','upcoming')+['title'=>'Dashboard','workspaceKey'=>'dashboard']);
    }

    public function listing(string $resource, string $title): void
    {
        \view('listing',['resource'=>$resource,'title'=>$title,'workspaceKey'=>$resource]);
    }

    public function show(int $id, string $tab = 'overview'): void
    {
        $show = Shows::find($id);
        $resource = match($tab) {'orders','revisions'=>'revisions','pulls','returns'=>'operations','repairs'=>'repairs','files'=>'files','history'=>'history','notes'=>'notes',default=>null};
        \view('shows/workspace',compact('show','tab','resource')+['title'=>$show['name'],'workspaceKey'=>'show:' . $id]);
    }

    public function showForm(?int $id = null): void
    {
        $show = $id?Shows::find($id):null;
        \view('shows/form',compact('show')+['title'=>$show?'Edit ' . $show['name']:'Add Show','workspaceKey'=>$show?'show:' . $id:'new-show','workspaceLabel'=>$show['name'] ?? 'Add Show']);
    }

    public function showSave(?int $id = null): void
    {
        $showId = Shows::save(\input(),$id);
        \json_response(['redirect'=>'/shows/' . $showId]);
    }

    public function revision(int $id): void
    {
        $revision = Orders::revision($id);
        $show = Shows::find((int)$revision['show_id']);
        $lines = Orders::lines($id);
        $issues = Planning::validate($lines,$id);
        \view('orders/revision',compact('revision','show','lines','issues')+['title'=>$revision['number'] . ' R' . $revision['revision_number'],'workspaceKey'=>'revision:' . $id]);
    }

    public function order(int $id): void
    {
        $order = DB::one('SELECT o.*,s.name AS show_name FROM shop_orders o JOIN shows s ON s.id=o.show_id WHERE o.id=?',[$id]) ?? throw new HttpException(404,'Shop order not found.');
        \view('orders/order',compact('order')+['resource'=>'revisions','title'=>$order['number'],'workspaceKey'=>'order:' . $id]);
    }

    public function orderForm(int $showId, ?int $orderId = null): void
    {
        $show = Shows::find($showId);
        Shows::editable($show);
        if ($orderId && !DB::one('SELECT id FROM shop_orders WHERE id=? AND show_id=?',[$orderId,$showId])) {
            throw new HttpException(404,'Shop order not found.');
        }
        $types = DB::all('SELECT id,name FROM item_types ORDER BY name');
        $packages = DB::all('SELECT id,name FROM packages ORDER BY name');
        \view('orders/form',compact('show','orderId','types','packages')+['title'=>$orderId?'Add Revision':'Add Shop Order','workspaceKey'=>'show:' . $showId,'workspaceLabel'=>$show['name']]);
    }

    public function orderSave(int $showId, ?int $orderId = null): void
    {
        $result = Orders::create($showId,\input(),$orderId);
        \json_response(['redirect'=>'/revisions/' . $result['revision_id'],'issues'=>$result['issues']]);
    }

    public function table(): void
    {
        \json_response(Tables::query(Validation::text($_GET['resource'] ?? '', 'Resource',40),$_GET));
    }

    public function workspaceSave(): void
    {
        $tabs = \input()['tabs'] ?? [];
        if (!is_array($tabs) || count($tabs)>30) {
            throw new HttpException(422,'Workspaces are limited to 30 open tabs.');
        }
        $clean = [];
        foreach ($tabs as $tab) {
            if (!is_array($tab) || !preg_match('#^/(?:dashboard|shows|orders|revisions|inventory|repairs|reports|settings|profile|users|operations)(?:/[a-z0-9/-]+)?$#D',$tab['url'] ?? '')) {
                throw new HttpException(422,'Invalid workspace URL.');
            }
            $clean[] = ['key'=>Validation::text($tab['key'] ?? '', 'Workspace key',100),'label'=>Validation::text($tab['label'] ?? '', 'Workspace label',200),'url'=>$tab['url']];
        }
        DB::execute('INSERT INTO user_workspaces(user_id,tabs) VALUES (?,?) ON DUPLICATE KEY UPDATE tabs=VALUES(tabs)',
            [Auth::user()['id'],json_encode($clean,JSON_THROW_ON_ERROR)]);
        \json_response(['message'=>'Workspace saved.']);
    }

    public function savedViews(): void
    {
        $resource = Validation::text($_GET['resource'] ?? '', 'Resource',40);
        Permissions::require(Tables::definition($resource)['permission']);
        \json_response(['views'=>DB::all('SELECT id,name,configuration FROM saved_views WHERE user_id=? AND resource=? ORDER BY name',[Auth::user()['id'],$resource])]);
    }

    public function saveView(): void
    {
        $data = \input();
        $resource = Validation::text($data['resource'] ?? '', 'Resource',40);
        Permissions::require(Tables::definition($resource)['permission']);
        $configuration = $data['configuration'] ?? null;
        if (!is_array($configuration) || strlen(json_encode($configuration,JSON_THROW_ON_ERROR))>10000) {
            throw new HttpException(422,'Invalid saved view configuration.');
        }
        $columns = Tables::definition($resource)['columns'];
        if (!is_array($configuration['hidden'] ?? []) || !is_array($configuration['filter'] ?? [])) {
            throw new HttpException(422,'Invalid saved view column settings.');
        }
        $clean = [
            'q'=>Validation::text($configuration['q'] ?? '', 'View search',200,false),
            'sort'=>Validation::choice($configuration['sort'] ?? '',['',...$columns],'sort column'),
            'direction'=>Validation::choice($configuration['direction'] ?? 'asc',['asc','desc'],'sort direction'),
            'limit'=>(int)Validation::choice((string)($configuration['limit'] ?? 25),['10','25','50','100'],'page size'),
            'hidden'=>[], 'filter'=>[], 'page'=>1,
        ];
        foreach ($configuration['hidden'] ?? [] as $column) {
            $clean['hidden'][] = Validation::choice($column,$columns,'hidden column');
        }
        foreach ($configuration['filter'] ?? [] as $column=>$value) {
            Validation::choice($column,$columns,'filter column');
            $clean['filter'][$column] = Validation::text($value,'Filter value',200,false);
        }
        DB::execute('INSERT INTO saved_views(user_id,resource,name,configuration) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE configuration=VALUES(configuration)',
            [Auth::user()['id'],$resource,Validation::text($data['name'] ?? '', 'View name',100),json_encode($clean,JSON_THROW_ON_ERROR)]);
        \json_response(['message'=>'View saved.']);
    }

    public function search(): void
    {
        $query = Validation::text($_GET['q'] ?? '', 'Search',200,false);
        if (mb_strlen($query)<2) {
            \json_response(['results'=>[]]);
        }
        $results = [];
        foreach (Tables::definitions() as $resource=>$definition) {
            if (in_array($resource,['revision_lines','history','notes','operations','documents'],true)) {
                continue;
            }
            if (!Permissions::allows($definition['permission'])) {
                continue;
            }
            $table = Tables::query($resource,['q'=>$query,'limit'=>10]);
            foreach (array_slice($table['rows'],0,4) as $row) {
                $results[] = ['label'=>(string)$row[$definition['columns'][0]],'resource'=>$resource,
                    'url'=>$definition['path']?$definition['path'] . $row['id']:match($resource) {'categories'=>'/inventory/categories','rules'=>'/inventory/rules',default=>'/shows/' . ($row['show_id'] ?? '')}];
            }
        }
        \json_response(['results'=>$results]);
    }
}
