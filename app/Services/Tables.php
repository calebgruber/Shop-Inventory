<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;
use App\Middleware\Permissions;

final class Tables
{
    public static function definitions(): array
    {
        return [
            'shows'=>['permission'=>'shows.view','sql'=>'SELECT id,name,theatre,status,prep_start,return_date FROM shows','columns'=>['name','theatre','status','prep_start','return_date'],'path'=>'/shows/'],
            'assets'=>['permission'=>'inventory.view','sql'=>'SELECT a.id,a.asset_tag,a.serial_number,a.state,i.name AS item_type,i.id AS item_type_id,f.name AS subcategory,c.name AS category FROM assets a JOIN item_types i ON i.id=a.item_type_id JOIN folders f ON f.id=i.folder_id JOIN folders c ON c.id=f.parent_id','columns'=>['asset_tag','serial_number','state','item_type','subcategory','category'],'path'=>'/inventory/assets/'],
            'repairs'=>['permission'=>'reports.view','sql'=>'SELECT r.id,a.asset_tag,r.description,r.status,s.name AS show_name,r.created_at,r.show_id FROM repairs r JOIN assets a ON a.id=r.asset_id LEFT JOIN shows s ON s.id=r.show_id','columns'=>['asset_tag','description','status','show_name','created_at'],'path'=>'/repairs/'],
            'revisions'=>['permission'=>'shows.view','sql'=>"SELECT r.id,o.show_id,o.id AS order_id,o.number,o.title,r.revision_number,ap.status,r.created_at,
                (SELECT COALESCE(SUM(quantity),0) FROM revision_lines l WHERE l.revision_id=r.id) AS requested,
                (SELECT COUNT(*) FROM assignments a JOIN revision_lines l ON l.id=a.line_id WHERE l.revision_id=r.id) AS pulled
                FROM revisions r JOIN shop_orders o ON o.id=r.order_id JOIN revision_approvals ap ON ap.revision_id=r.id",'columns'=>['number','title','revision_number','status','requested','pulled','created_at'],'path'=>'/revisions/'],
            'revision_lines'=>['permission'=>'shows.view','sql'=>'SELECT l.id,l.revision_id,i.name AS equipment,l.quantity,p.name AS package,l.start_date,l.return_date,
                (SELECT COUNT(*) FROM assignments a WHERE a.line_id=l.id) AS pulled,
                (SELECT COUNT(*) FROM assignments a WHERE a.line_id=l.id AND a.ended_at IS NOT NULL) AS returned
                FROM revision_lines l JOIN item_types i ON i.id=l.item_type_id LEFT JOIN packages p ON p.id=l.package_id','columns'=>['equipment','quantity','package','start_date','return_date','pulled','returned'],'path'=>null],
            'operations'=>['permission'=>'shows.view','sql'=>'SELECT op.id,op.show_id,op.type,op.mode,u.name AS user_name,op.created_at FROM operations op JOIN users u ON u.id=op.created_by','columns'=>['type','mode','user_name','created_at'],'path'=>'/operations/'],
            'files'=>['permission'=>'shows.view','sql'=>'SELECT id,show_id,original_name,mime,size_bytes,created_at FROM files','columns'=>['original_name','mime','size_bytes','created_at'],'path'=>'/files/'],
            'history'=>['permission'=>'shows.view','sql'=>"SELECT a.id,a.entity_id AS show_id,a.action,u.name AS user_name,a.details,a.created_at FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity_type='show'",'columns'=>['action','user_name','details','created_at'],'path'=>null],
            'notes'=>['permission'=>'shows.view','sql'=>'SELECT n.id,n.show_id,n.body,u.name AS author,n.created_at FROM show_notes n JOIN users u ON u.id=n.created_by','columns'=>['body','author','created_at'],'path'=>null],
            'packages'=>['permission'=>'inventory.view','sql'=>'SELECT p.id,p.name,p.description,(SELECT COUNT(*) FROM package_components c WHERE c.package_id=p.id) AS components FROM packages p','columns'=>['name','description','components'],'path'=>'/inventory/packages/'],
            'rules'=>['permission'=>'inventory.view','sql'=>'SELECT r.id,r.name,s.name AS source_type,r.source_quantity,d.name AS dependency_type,r.dependency_quantity,r.mode,r.active FROM equipment_rules r JOIN item_types s ON s.id=r.source_type_id JOIN item_types d ON d.id=r.dependency_type_id','columns'=>['name','source_type','source_quantity','dependency_type','dependency_quantity','mode','active'],'path'=>null],
            'categories'=>['permission'=>'inventory.view','sql'=>'SELECT f.id,f.name,p.name AS parent,f.sort_position FROM folders f LEFT JOIN folders p ON p.id=f.parent_id','columns'=>['name','parent','sort_position'],'path'=>null],
            'users'=>['permission'=>'users.view','sql'=>'SELECT id,username,name,email,role,active,created_at FROM users','columns'=>['username','name','email','role','active','created_at'],'path'=>'/users/'],
            'documents'=>['permission'=>'reports.view','sql'=>'SELECT d.id,d.type,d.entity_id,d.created_at,d.sha256,u.name AS generated_by FROM documents d JOIN users u ON u.id=d.created_by','columns'=>['id','type','created_at','generated_by','sha256'],'path'=>'/documents/'],
        ];
    }

    public static function definition(string $resource): array
    {
        return self::definitions()[$resource] ?? throw new HttpException(404,'Table not found.');
    }

    public static function query(string $resource, array $query): array
    {
        $definition = self::definition($resource);
        Permissions::require($definition['permission']);
        if (!is_array($query['filter'] ?? [])) {
            throw new HttpException(422,'Invalid table filters.');
        }
        $params = [];
        $conditions = ['1=1'];
        $term = Validation::text($query['q'] ?? '', 'Search',200,false);
        if ($term!=='') {
            $conditions[] = '(' . implode(' OR ',array_map(fn(string $c): string => "CAST(t.$c AS CHAR) LIKE ? ESCAPE '!'",$definition['columns'])) . ')';
            foreach ($definition['columns'] as $_) {
                $params[] = '%' . str_replace(['!','%','_'],['!!','!%','!_'],$term) . '%';
            }
        }
        if (!empty($query['show_id']) && in_array($resource,['repairs','revisions','operations','files','history','notes'],true)) {
            $conditions[] = 't.show_id=?';
            $params[] = Validation::positive($query['show_id'],'Show');
        }
        if (!empty($query['order_id']) && $resource==='revisions') {
            $conditions[] = 't.order_id=?';
            $params[] = Validation::positive($query['order_id'],'Shop order');
        }
        if (!empty($query['revision_id']) && $resource==='revision_lines') {
            $conditions[] = 't.revision_id=?';
            $params[] = Validation::positive($query['revision_id'],'Revision');
        }
        if (!empty($query['item_type_id']) && $resource==='assets') {
            $conditions[] = 't.item_type_id=?';
            $params[] = Validation::positive($query['item_type_id'],'Item type');
        }
        if ($resource==='documents') {
            if (!empty($query['document_type'])) {
                $conditions[] = 't.type=?';
                $params[] = Validation::text($query['document_type'],'Document type',40);
            }
            if (!empty($query['entity_id'])) {
                $conditions[] = 't.entity_id=?';
                $params[] = Validation::positive($query['entity_id'],'Record');
            }
        }
        foreach ($definition['columns'] as $column) {
            $value = $query['filter'][$column] ?? '';
            if ($value!=='') {
                $conditions[] = "t.$column=?";
                $params[] = Validation::text($value,'Filter',200);
            }
        }
        $where = implode(' AND ',$conditions);
        $sort = in_array($query['sort'] ?? '',$definition['columns'],true)?$query['sort']:'id';
        $direction = ($query['direction'] ?? '')==='asc'?'ASC':'DESC';
        $page = max(1,min(100000,(int)($query['page'] ?? 1)));
        $limit = in_array((int)($query['limit'] ?? 25),[10,25,50,100],true)?(int)($query['limit'] ?? 25):25;
        $base = '(' . $definition['sql'] . ') t';
        $total = (int)DB::one("SELECT COUNT(*) AS total FROM $base WHERE $where",$params)['total'];
        $page = min($page,max(1,(int)ceil($total/$limit)));
        $offset = ($page-1)*$limit;
        $rows = DB::all("SELECT t.* FROM $base WHERE $where ORDER BY t.$sort $direction,t.id $direction LIMIT $limit OFFSET $offset",$params);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit,'columns'=>$definition['columns'],'path'=>$definition['path']];
    }
}
