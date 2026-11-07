<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\Database as DB;
use App\Services\Inventory;
use App\Services\Validation;
use App\Services\HttpException;

final class InventoryController
{
    public function categories(): void
    {
        $folders = DB::all('SELECT * FROM folders ORDER BY sort_position,id');
        $types = DB::all('SELECT * FROM item_types ORDER BY sort_position,id');
        \view('inventory/categories',compact('folders','types')+['title'=>'Inventory Categories','workspaceKey'=>'categories']);
    }

    public function form(string $resource,?int $id = null): void
    {
        $folders = DB::all('SELECT f.*,p.name AS parent_name FROM folders f LEFT JOIN folders p ON p.id=f.parent_id ORDER BY f.sort_position,f.name');
        $types = DB::all('SELECT id,name FROM item_types ORDER BY name');
        $record = $id?DB::one('SELECT * FROM ' . ($resource==='category'?'folders':'item_types') . ' WHERE id=?',[$id]):null;
        if ($id && !$record) {
            throw new HttpException(404,'Inventory record not found.');
        }
        \view('inventory/form',compact('resource','folders','types','record')+['title'=>($id?'Edit ':'Add ') . $resource,'workspaceKey'=>'inventory-' . $resource . ($id?':' . $id:'')]);
    }

    public function save(string $resource,?int $id = null): void
    {
        $data = \input();
        if ($id) {
            Inventory::update($resource,$id,$data);
            \json_response(['redirect'=>'/inventory/categories']);
        }
        if ($resource==='package') {
            $data['components'] = $data['lines'] ?? [];
        }
        $id = match ($resource) {'category'=>Inventory::folder($data),'item-type'=>Inventory::itemType($data),'package'=>Inventory::package($data),'rule'=>Inventory::rule($data)};
        \json_response(['redirect'=>match($resource) {'package'=>'/inventory/packages/' . $id,'rule'=>'/inventory/rules',default=>'/inventory/categories'}]);
    }

    public function importForm(): void
    {
        $types = DB::all('SELECT id,name FROM item_types ORDER BY name');
        \view('inventory/import',compact('types')+['title'=>'Import Serialized Assets','workspaceKey'=>'import']);
    }

    public function import(): void
    {
        $data = \input();
        $format = Validation::choice($data['format'] ?? 'csv',['csv','text'],'import format');
        $content = Validation::text($data['content'] ?? '', 'Import data',2097152,false);
        if (isset($_FILES['csv']) && $_FILES['csv']['error']!==UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['csv'];
            if ($file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name'])>2097152) {
                throw new HttpException(422,'CSV upload failed or exceeds 2 MB.');
            }
            $content = file_get_contents($file['tmp_name']);
            $format = 'csv';
        }
        $count = Inventory::import(Validation::positive($data['item_type_id'] ?? 0,'Item type'),$content,$format);
        \json_response(['message'=>"$count assets imported.",'redirect'=>'/inventory']);
    }

    public function asset(int $id): void
    {
        $asset = DB::one('SELECT a.*,i.name AS item_name FROM assets a JOIN item_types i ON i.id=a.item_type_id WHERE a.id=?',[$id]) ?? throw new HttpException(404,'Asset not found.');
        $events = DB::all('SELECT e.*,s.name AS show_name,u.name AS user_name FROM asset_events e LEFT JOIN shows s ON s.id=e.show_id JOIN users u ON u.id=e.created_by WHERE e.asset_id=? ORDER BY e.created_at,e.id',[$id]);
        $assignments = DB::all('SELECT a.*,s.name AS show_name FROM assignments a JOIN shows s ON s.id=a.show_id WHERE a.asset_id=? ORDER BY a.id',[$id]);
        \view('inventory/asset',compact('asset','events','assignments')+['title'=>$asset['asset_tag'],'workspaceKey'=>'asset:' . $id]);
    }

    public function package(int $id): void
    {
        $package = DB::one('SELECT * FROM packages WHERE id=?',[$id]) ?? throw new HttpException(404,'Package not found.');
        $components = DB::all('SELECT c.*,i.name AS item_name FROM package_components c JOIN item_types i ON i.id=c.item_type_id WHERE c.package_id=?',[$id]);
        \view('inventory/package',compact('package','components')+['title'=>$package['name'],'workspaceKey'=>'package:' . $id]);
    }
}
