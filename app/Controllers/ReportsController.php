<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\Database as DB;
use App\Services\Documents;
use App\Services\Validation;
use App\Services\HttpException;
use App\Services\Audit;
use App\Middleware\Permissions;

final class ReportsController
{
    public function index(): void
    {
        \view('reports/index',['title'=>'Reports','workspaceKey'=>'reports']);
    }

    public function timeline(): void
    {
        $categories = DB::all('SELECT * FROM folders WHERE parent_id IS NULL ORDER BY sort_position');
        $subcategories = DB::all('SELECT * FROM folders WHERE parent_id IS NOT NULL ORDER BY sort_position');
        $types = DB::all('SELECT id,name FROM item_types ORDER BY name');
        $shows = DB::all('SELECT id,name,theatre FROM shows ORDER BY name');
        \view('reports/timeline',compact('categories','subcategories','types','shows')+['title'=>'Equipment Timeline','workspaceKey'=>'timeline']);
    }

    public function timelineData(): void
    {
        $start = Validation::date($_GET['start'] ?? gmdate('Y-m-d'),'Timeline start');
        $end = Validation::date($_GET['end'] ?? gmdate('Y-m-d',time()+30*86400),'Timeline end');
        if ($end<$start || strtotime($end)-strtotime($start)>180*86400) {
            throw new HttpException(422,'Timeline ranges must be 0-180 days.');
        }
        $where = ['1=1'];
        $params = [];
        $filters = ['category'=>'c.id','subcategory'=>'f.id','item_type'=>'i.id','status'=>'a.state','q'=>'a.asset_tag'];
        foreach ($filters as $key=>$column) {
            if (!empty($_GET[$key])) {
                $where[] = "$column " . ($key==='q'?'LIKE ?':'=?');
                $params[] = $key==='q'?'%' . Validation::text($_GET[$key],'Search',80) . '%':Validation::text($_GET[$key],'Filter',80);
            }
        }
        if (!empty($_GET['show'])) {
            $where[] = 'EXISTS(SELECT 1 FROM assignments z WHERE z.asset_id=a.id AND z.show_id=?)';
            $params[] = Validation::positive($_GET['show'],'Show');
        }
        if (!empty($_GET['theatre'])) {
            $where[] = 'EXISTS(SELECT 1 FROM assignments z JOIN shows sh ON sh.id=z.show_id WHERE z.asset_id=a.id AND sh.theatre=?)';
            $params[] = Validation::text($_GET['theatre'],'Theatre');
        }
        $base = 'FROM assets a JOIN item_types i ON i.id=a.item_type_id JOIN folders f ON f.id=i.folder_id JOIN folders c ON c.id=f.parent_id WHERE ' . implode(' AND ',$where);
        $total = (int)DB::one("SELECT COUNT(*) AS total $base",$params)['total'];
        $page = max(1,min((int)($_GET['page'] ?? 1),max(1,(int)ceil($total/50))));
        $offset = ($page-1)*50;
        $assets = DB::all("SELECT a.id,a.asset_tag,a.state,i.name AS item_name,i.id AS item_type_id $base ORDER BY i.sort_position,a.asset_tag LIMIT 50 OFFSET $offset",$params);
        $ids = array_column($assets,'id');
        $assignments = $repairs = $events = [];
        if ($ids) {
            $marks = implode(',',array_fill(0,count($ids),'?'));
            $assignments = DB::all("SELECT x.*,s.name AS show_name,op.type,r.revision_number FROM assignments x JOIN shows s ON s.id=x.show_id
                JOIN operations op ON op.id=x.operation_id LEFT JOIN revisions r ON r.id=op.revision_id WHERE x.asset_id IN ($marks)
                AND x.start_date<=? AND (x.ended_at IS NULL OR DATE(x.ended_at)>=?) ORDER BY x.id",[...$ids,$end,$start]);
            $repairs = DB::all("SELECT id,asset_id,status,created_at,completed_at FROM repairs WHERE asset_id IN ($marks) AND DATE(created_at)<=? AND (completed_at IS NULL OR DATE(completed_at)>=?)",[...$ids,$end,$start]);
            $events = DB::all("SELECT id,asset_id,state,created_at,details FROM asset_events WHERE asset_id IN ($marks) AND DATE(created_at) BETWEEN ? AND ? ORDER BY id",[...$ids,$start,$end]);
        }
        $planning = [];
        foreach (array_unique(array_column($assets,'item_type_id')) as $typeId) {
            $planning[] = ['item_type_id'=>$typeId,'availability'=>\App\Services\Planning::availability((int)$typeId,$start,$end),
                'commitments'=>\App\Services\Planning::commitments((int)$typeId)];
        }
        \json_response(compact('assets','assignments','repairs','events','planning','start','end','total','page'));
    }

    public function generate(): void
    {
        $data = \input();
        $type = Validation::choice($data['type'] ?? '',[...Documents::REPORTS,'show','archive','revision','operation','repair'],'document type');
        $id = in_array($type,Documents::REPORTS,true)?0:Validation::positive($data['entity_id'] ?? 0,'Record');
        $filters = [];
        if ($type==='timeline' && isset($data['filters'])) {
            if (!is_array($data['filters'])) {
                throw new HttpException(422,'Invalid timeline report criteria.');
            }
            foreach (['show','theatre','category','subcategory','item_type','status','q'] as $key) {
                $filters[$key] = Validation::text($data['filters'][$key] ?? '', 'Report filter',200,false);
            }
            $filters['start'] = Validation::date($data['filters']['start'] ?? '', 'Timeline start');
            $filters['end'] = Validation::date($data['filters']['end'] ?? '', 'Timeline end');
            if ($filters['end']<$filters['start'] || strtotime($filters['end'])-strtotime($filters['start'])>180*86400) {
                throw new HttpException(422,'Timeline ranges must be 0-180 days.');
            }
        }
        $document = DB::transaction(fn(): int => Documents::generate($type,$id,$filters));
        \json_response(['download'=>'/documents/' . $document,'message'=>'PDF generated.']);
    }

    public function download(int $id): void
    {
        $document = DB::one('SELECT * FROM documents WHERE id=?',[$id]) ?? throw new HttpException(404,'Document not found.');
        $path = ROOT . '/storage/pdfs/' . basename($document['stored_name']);
        if (!is_file($path) || !hash_equals($document['sha256'],hash_file('sha256',$path))) {
            throw new \RuntimeException('Document is missing or failed integrity verification.');
        }
        Audit::record('document.downloaded','document',$id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $document['type'] . '-' . $document['entity_id'] . '-' . $id . '.pdf"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    public function file(int $id): void
    {
        $file = DB::one('SELECT * FROM files WHERE id=?',[$id]) ?? throw new HttpException(404,'File not found.');
        $path = ROOT . '/storage/uploads/' . basename($file['stored_name']);
        if (!is_file($path)) {
            throw new \RuntimeException('The uploaded file is missing.');
        }
        Audit::record('file.downloaded','show',(int)$file['show_id'],['file_id'=>$id]);
        header('Content-Type: ' . $file['mime']);
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($file['original_name']));
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }
}
