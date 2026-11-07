<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;

final class Inventory
{
    public static function folder(array $data): int
    {
        $name = Validation::text($data['name'] ?? '', 'Category name',160);
        $parent = empty($data['parent_id'])?null:Validation::positive($data['parent_id'],'Parent category');
        return DB::transaction(function () use ($name,$parent): int {
            if ($parent) {
                $category = DB::one('SELECT * FROM folders WHERE id=? FOR UPDATE',[$parent]);
                if (!$category || $category['parent_id']!==null) {
                    throw new HttpException(422,'Subcategories must belong to a top-level category.');
                }
            }
            $position = DB::one('SELECT COALESCE(MAX(sort_position),0)+1 AS position FROM folders WHERE parent_id <=> ?',[$parent])['position'];
            $id = DB::insert('INSERT INTO folders(parent_id,name,sort_position) VALUES (?,?,?)',[$parent,$name,$position]);
            Audit::record('category.created','category',$id);
            return $id;
        });
    }

    public static function itemType(array $data): int
    {
        $folder = Validation::positive($data['folder_id'] ?? 0,'Subcategory');
        $prefix = strtoupper(Validation::text($data['prefix'] ?? '', 'Asset prefix',24));
        if (!preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/D',$prefix)) {
            throw new HttpException(422,'Asset prefixes contain only letters, numbers and internal hyphens.');
        }
        return DB::transaction(function () use ($data,$folder,$prefix): int {
            $parent = DB::one('SELECT * FROM folders WHERE id=? FOR UPDATE',[$folder]);
            if (!$parent || $parent['parent_id']===null) {
                throw new HttpException(422,'Item types belong to a subcategory.');
            }
            $position = DB::one('SELECT COALESCE(MAX(sort_position),0)+1 AS position FROM item_types WHERE folder_id=?',[$folder])['position'];
            $id = DB::insert('INSERT INTO item_types(folder_id,name,prefix,sort_position) VALUES (?,?,?,?)',
                [$folder,Validation::text($data['name'] ?? '', 'Item type',160),$prefix,$position]);
            Audit::record('item_type.created','item_type',$id);
            return $id;
        });
    }

    public static function reorder(array $data): void
    {
        $resource = Validation::choice($data['resource'] ?? '',['folders','item_types'],'sortable resource');
        $ids = $data['ids'] ?? [];
        if (!is_array($ids) || !$ids || count($ids)>1000 || count(array_unique($ids))!==count($ids)) {
            throw new HttpException(422,'Sort list must contain unique record IDs.');
        }
        $ids = array_map(fn(mixed $id): int => Validation::positive($id,'Record'),$ids);
        $parent = empty($data['parent_id']) ? null : Validation::positive($data['parent_id'],'Parent');
        $column = $resource==='folders'?'parent_id':'folder_id';
        DB::transaction(function () use ($resource,$column,$parent,$ids): void {
            $rows = DB::all("SELECT id FROM $resource WHERE $column <=> ? ORDER BY id FOR UPDATE",[$parent]);
            $expected = array_map('intval',array_column($rows,'id'));
            $provided = $ids;
            sort($provided);
            if ($provided!==$expected) {
                throw new HttpException(422,'Reordering must include all siblings within the same category. Refresh the list.');
            }
            foreach ($ids as $position=>$id) {
                DB::execute("UPDATE $resource SET sort_position=? WHERE id=?",[$position,$id]);
            }
            Audit::record('inventory.reordered','category',(int)$parent,['resource'=>$resource,'ids'=>$ids]);
        });
    }

    public static function update(string $resource,int $id,array $data): void
    {
        DB::transaction(function () use ($resource,$id,$data): void {
            $name = Validation::text($data['name'] ?? '', 'Name',160);
            if ($resource==='category') {
                $folder = DB::one('SELECT * FROM folders WHERE id=? FOR UPDATE',[$id]) ?? throw new HttpException(404,'Category not found.');
                $parent = empty($data['parent_id'])?null:Validation::positive($data['parent_id'],'Parent category');
                if ($parent) {
                    $root = DB::one('SELECT * FROM folders WHERE id=? FOR UPDATE',[$parent]);
                    if (!$root || $root['parent_id']!==null || $parent===$id ||
                        DB::one('SELECT id FROM folders WHERE parent_id=? LIMIT 1',[$id])) {
                        throw new HttpException(422,'Choose a top-level parent. Categories with children cannot become subcategories.');
                    }
                } elseif ($folder['parent_id']!==null && DB::one('SELECT id FROM item_types WHERE folder_id=? LIMIT 1',[$id])) {
                    throw new HttpException(422,'Subcategories with item types cannot become top-level categories.');
                }
                DB::execute('UPDATE folders SET name=?,parent_id=? WHERE id=?',[$name,$parent,$id]);
                Audit::record('category.updated','category',$id,['before'=>$folder,'name'=>$name,'parent_id'=>$parent]);
            } else {
                $type = DB::one('SELECT * FROM item_types WHERE id=? FOR UPDATE',[$id]) ?? throw new HttpException(404,'Item type not found.');
                $folder = Validation::positive($data['folder_id'] ?? 0,'Subcategory');
                $parent = DB::one('SELECT * FROM folders WHERE id=?',[$folder]);
                if (!$parent || $parent['parent_id']===null) {
                    throw new HttpException(422,'Item types must belong to a subcategory.');
                }
                $prefix = strtoupper(Validation::text($data['prefix'] ?? '', 'Prefix',24));
                if (!preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/D',$prefix)) {
                    throw new HttpException(422,'Invalid asset prefix.');
                }
                if ($prefix!==$type['prefix'] && DB::one('SELECT id FROM assets WHERE item_type_id=? LIMIT 1',[$id])) {
                    throw new HttpException(422,'An asset prefix cannot change after serialized assets exist.');
                }
                if ($name!==$type['name'] && DB::one('SELECT id FROM revision_lines WHERE item_type_id=? LIMIT 1',[$id])) {
                    throw new HttpException(422,'An item type used on historical requests cannot be renamed. Create a new item type.');
                }
                DB::execute('UPDATE item_types SET name=?,folder_id=?,prefix=? WHERE id=?',[$name,$folder,$prefix,$id]);
                Audit::record('item_type.updated','item_type',$id,['before'=>$type,'name'=>$name,'folder_id'=>$folder]);
            }
        });
    }

    public static function remove(string $resource,int $id): void
    {
        DB::transaction(function () use ($resource,$id): void {
            if ($resource==='category') {
                $row = DB::one('SELECT * FROM folders WHERE id=? FOR UPDATE',[$id]) ?? throw new HttpException(404,'Category not found.');
                if (DB::one('SELECT id FROM folders WHERE parent_id=? LIMIT 1',[$id]) || DB::one('SELECT id FROM item_types WHERE folder_id=? LIMIT 1',[$id])) {
                    throw new HttpException(422,'Only empty categories and subcategories can be deleted.');
                }
                DB::execute('DELETE FROM folders WHERE id=?',[$id]);
            } else {
                $row = DB::one('SELECT * FROM item_types WHERE id=? FOR UPDATE',[$id]) ?? throw new HttpException(404,'Item type not found.');
                if (DB::one('SELECT id FROM assets WHERE item_type_id=? LIMIT 1',[$id]) ||
                    DB::one('SELECT id FROM revision_lines WHERE item_type_id=? LIMIT 1',[$id]) ||
                    DB::one('SELECT package_id FROM package_components WHERE item_type_id=? LIMIT 1',[$id]) ||
                    DB::one('SELECT id FROM equipment_rules WHERE source_type_id=? OR dependency_type_id=? LIMIT 1',[$id,$id])) {
                    throw new HttpException(422,'Item types with assets, orders, package components, or rules cannot be deleted.');
                }
                DB::execute('DELETE FROM item_types WHERE id=?',[$id]);
            }
            Audit::record($resource . '.deleted',$resource,$id,['record'=>$row]);
        });
    }

    public static function assetAction(int $id,array $data): void
    {
        $action = Validation::choice($data['action'] ?? '',['recover','repair'],'asset action');
        $notes = Validation::text($data['notes'] ?? '', 'Asset condition notes',10000);
        DB::transaction(function () use ($id,$action,$notes): void {
            $asset = DB::one('SELECT * FROM assets WHERE id=?',[$id]) ?? throw new HttpException(404,'Asset not found.');
            Orders::lockTypes([['item_type_id'=>$asset['item_type_id']]]);
            $asset = DB::one('SELECT * FROM assets WHERE id=? FOR UPDATE',[$id]);
            if ($action==='recover' && $asset['state']!=='Missing') {
                throw new HttpException(422,'Only missing assets can be marked as recovered.');
            }
            if ($action==='repair' && $asset['state']!=='Available') {
                throw new HttpException(422,'For checked-out equipment, log damage during its return. Only available assets can be sent directly to repair.');
            }
            $state = $action==='recover'?'Available':'Repair';
            DB::execute('UPDATE assets SET state=? WHERE id=?',[$state,$id]);
            DB::execute('INSERT INTO asset_events(asset_id,state,details,created_by) VALUES (?,?,?,?)',
                [$id,$state,json_encode(['action'=>$action,'notes'=>$notes],JSON_THROW_ON_ERROR),Auth::user()['id']]);
            Audit::record('asset.' . $action,'asset',$id,['notes'=>$notes,'before'=>$asset['state'],'after'=>$state]);
            if ($action==='repair') {
                $repair = DB::insert('INSERT INTO repairs(asset_id,description,created_by) VALUES (?,?,?)',[$id,$notes,Auth::user()['id']]);
                Audit::record('repair.created','repair',$repair);
                Documents::generate('repair',$repair);
            } else {
                Documents::generate('missing',0);
            }
        });
    }

    public static function parseImport(string $content, string $format): array
    {
        if (strlen($content)>2097152) {
            throw new HttpException(413,'Imports are limited to 2 MB.');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/',trim($content));
        if (!$lines || count($lines)>5000) {
            throw new HttpException(422,'Import between 1 and 5000 assets at a time.');
        }
        $rows = [];
        foreach ($lines as $index=>$line) {
            if (trim($line)==='') {
                continue;
            }
            if ($format==='csv') {
                $values = str_getcsv($line,',','"','');
                if ($index===0 && strtolower(trim($values[0]))==='asset_tag') {
                    continue;
                }
                if (count($values)>2) {
                    throw new HttpException(422,'CSV columns must be asset_tag,serial_number.');
                }
                $rows[] = ['asset_tag'=>trim($values[0] ?? ''),'serial_number'=>trim($values[1] ?? '')];
            } else {
                $rows[] = ['asset_tag'=>'','serial_number'=>trim($line)];
            }
        }
        if (!$rows) {
            throw new HttpException(422,'No asset records were found.');
        }
        return $rows;
    }

    public static function import(int $typeId, string $content, string $format): int
    {
        Validation::choice($format,['csv','text'],'import format');
        $rows = self::parseImport($content,$format);
        return DB::transaction(function () use ($typeId,$rows): int {
            $type = DB::one('SELECT * FROM item_types WHERE id=? FOR UPDATE',[$typeId]) ?? throw new HttpException(404,'Item type not found.');
            $tags = array_column(DB::all('SELECT asset_tag FROM assets WHERE item_type_id=?',[$typeId]),'asset_tag');
            $next = 1;
            foreach ($tags as $tag) {
                if (preg_match('/^' . preg_quote($type['prefix'],'/') . '-(\d+)$/D',$tag,$match)) {
                    $next = max($next,(int)$match[1]+1);
                }
            }
            $seen = [];
            foreach ($rows as $row) {
                $tag = $row['asset_tag'];
                if ($tag==='') {
                    do {
                        $tag = $type['prefix'] . '-' . str_pad((string)$next++,4,'0',STR_PAD_LEFT);
                    } while (in_array($tag,$tags,true) || isset($seen[$tag]));
                }
                Validation::text($tag,'Asset tag',80);
                if (!preg_match('/^[A-Za-z0-9._-]+$/D',$tag) || isset($seen[$tag]) || DB::one('SELECT id FROM assets WHERE asset_tag=?',[$tag])) {
                    throw new HttpException(422,"Invalid or duplicate asset tag: $tag. Nothing was imported.");
                }
                $serial = Validation::text($row['serial_number'],'Serial number',160,false) ?: null;
                if ($serial && DB::one('SELECT id FROM assets WHERE item_type_id=? AND serial_number=?',[$typeId,$serial])) {
                    throw new HttpException(422,"Duplicate serial number: $serial. Nothing was imported.");
                }
                $seen[$tag] = true;
                $id = DB::insert('INSERT INTO assets(item_type_id,asset_tag,serial_number) VALUES (?,?,?)',[$typeId,$tag,$serial]);
                DB::execute("INSERT INTO asset_events(asset_id,state,details,created_by) VALUES (?,'Available',?,?)",[$id,json_encode(['source'=>'import'],JSON_THROW_ON_ERROR),Auth::user()['id']]);
                Audit::record('asset.created','asset',$id,['item_type_id'=>$typeId]);
            }
            Documents::generate('inventory',0);
            return count($rows);
        });
    }

    public static function package(array $data): int
    {
        $components = $data['components'] ?? [];
        if (!is_array($components) || !$components || count($components)>100) {
            throw new HttpException(422,'Packages require 1-100 item type components.');
        }
        return DB::transaction(function () use ($data,$components): int {
            $id = DB::insert('INSERT INTO packages(name,description,created_by) VALUES (?,?,?)',
                [Validation::text($data['name'] ?? '', 'Package name',160),Validation::text($data['description'] ?? '', 'Description',10000,false),Auth::user()['id']]);
            foreach ($components as $component) {
                DB::execute('INSERT INTO package_components(package_id,item_type_id,quantity) VALUES (?,?,?)',
                    [$id,Validation::positive($component['item_type_id'] ?? 0,'Component type'),Validation::positive($component['quantity'] ?? 0,'Component ratio')]);
            }
            Audit::record('package.created','package',$id,['components'=>$components]);
            Documents::generate('packages',0);
            return $id;
        });
    }

    public static function rule(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $source = Validation::positive($data['source_type_id'] ?? 0,'Source equipment');
            $dependency = Validation::positive($data['dependency_type_id'] ?? 0,'Accessory equipment');
            if ($source===$dependency) {
                throw new HttpException(422,'A rule cannot depend on its own item type.');
            }
            $id = DB::insert('INSERT INTO equipment_rules(name,source_type_id,source_quantity,dependency_type_id,dependency_quantity,mode) VALUES (?,?,?,?,?,?)',
                [Validation::text($data['name'] ?? '', 'Rule name',160),$source,Validation::positive($data['source_quantity'] ?? 0,'Source ratio'),
                    $dependency,Validation::positive($data['dependency_quantity'] ?? 0,'Accessory ratio'),Validation::choice($data['mode'] ?? 'warning',['warning','strict'],'rule mode')]);
            Audit::record('rule.created','rule',$id);
            Documents::generate('rules',0);
            return $id;
        });
    }
}
