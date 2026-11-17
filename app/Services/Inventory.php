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

    public static function parseCatalog(string $content): array
    {
        if ($content==='' || strlen($content)>2097152) {
            throw new HttpException(413,'Catalog imports must contain CSV data and be no larger than 2 MB.');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/','',$content);
        $stream = fopen('php://temp','w+b');
        if ($stream===false) {
            throw new \RuntimeException('Unable to parse the catalog CSV.');
        }
        try {
            fwrite($stream,$content);
            rewind($stream);
            $header = fgetcsv($stream,null,',','"','');
            $header = array_map(static fn($field): string => strtolower(trim((string)$field)),$header ?: []);
            $lighting = ['category','name','shop_quantity','unit','default_note','description'];
            $sound = ['category','category','name','sku','shop_quantity','unit','default_note','description'];
            $soundFormat = $header===$sound;
            if ($header!==$lighting && !$soundFormat) {
                throw new HttpException(422,'CSV headers must match either the lighting format (category, name, shop_quantity, unit, default_note, description) or sound format (category, category, name, sku, shop_quantity, unit, default_note, description).');
            }
            $rows = [];
            $totalQuantity = 0;
            $line = 1;
            while (($fields=fgetcsv($stream,null,',','"',''))!==false) {
                $line++;
                if ($fields===[null] || count($fields)===1 && trim((string)$fields[0])==='') {
                    continue;
                }
                $expected = $soundFormat?8:6;
                if (count($fields)!==$expected) {
                    throw new HttpException(422,"Catalog CSV row $line must contain exactly $expected columns.");
                }
                $category = $soundFormat?trim((string)$fields[0]):'Lighting';
                $subcategory = $soundFormat?trim((string)$fields[1]):trim((string)$fields[0]);
                $name = trim((string)$fields[$soundFormat?2:1]);
                $quantityValue = trim((string)$fields[$soundFormat?4:2]);
                if ($name==='' && ($quantityValue==='' || $quantityValue==='0')) {
                    continue;
                }
                if ($category==='' || $subcategory==='' || $name==='') {
                    throw new HttpException(422,"Catalog CSV row $line is missing its category or equipment name.");
                }
                if ($quantityValue!=='' && !preg_match('/^\d+$/D',$quantityValue)) {
                    throw new HttpException(422,"Catalog CSV row $line has an invalid shop quantity.");
                }
                $quantity = $quantityValue===''?0:(int)$quantityValue;
                if ($quantity>50000 || ($totalQuantity+=$quantity)>25000) {
                    throw new HttpException(422,'A catalog import may create at most 25,000 serialized assets.');
                }
                $unit = trim((string)$fields[$soundFormat?5:3]) ?: 'ea';
                $note = trim((string)$fields[$soundFormat?6:4]);
                $description = trim((string)$fields[$soundFormat?7:5]);
                $rows[] = [
                    'category'=>Validation::text($category,'Category',160),
                    'subcategory'=>Validation::text($subcategory,'Subcategory',160),
                    'name'=>Validation::text($name,'Item name',160),
                    'sku'=>$soundFormat?Validation::text(trim((string)$fields[3]),'SKU',80,false):'',
                    'shop_quantity'=>$quantity,
                    'unit'=>Validation::text($unit,'Unit',20),
                    'default_note'=>in_array($note,['','.'],true)?'':Validation::text($note,'Default note',10000,false),
                    'description'=>in_array($description,['','.'],true)?'':Validation::text($description,'Description',10000,false),
                ];
                if (count($rows)>5000) {
                    throw new HttpException(422,'Import between 1 and 5000 catalog rows at a time.');
                }
            }
        } finally {
            fclose($stream);
        }
        if (!$rows) {
            throw new HttpException(422,'No named catalog items were found.');
        }
        $aggregated = [];
        foreach ($rows as $row) {
            $key = json_encode(array_diff_key($row,['shop_quantity'=>true]),JSON_THROW_ON_ERROR);
            if (!isset($aggregated[$key])) {
                $aggregated[$key]=$row;
            } else {
                $aggregated[$key]['shop_quantity']+=$row['shop_quantity'];
                if ($aggregated[$key]['shop_quantity']>50000) {
                    throw new HttpException(422,'A catalog item exceeds the 50,000 unit import limit.');
                }
            }
        }
        return ['rows'=>array_values($aggregated),'row_count'=>count($rows),'asset_count'=>$totalQuantity];
    }

    public static function importCatalog(string $content): array
    {
        $contentHash = hash('sha256',preg_replace('/^\xEF\xBB\xBF/','',$content));
        $parsed = self::parseCatalog($content);
        $rows = $parsed['rows'];
        return DB::transaction(function () use ($contentHash,$parsed,$rows): array {
            if (DB::one('SELECT id FROM inventory_imports WHERE content_hash=? FOR UPDATE',[$contentHash])) {
                throw new HttpException(409,'This exact catalog CSV was already imported. No inventory was changed.');
            }
            $userId = (int)Auth::user()['id'];
            $categories = [];
            $subcategories = [];
            $createdTypes = 0;
            $addedAssets = 0;
            $typeResults = [];
            foreach ($rows as $row) {
                $categoryKey = mb_strtolower($row['category']);
                if (!isset($categories[$categoryKey])) {
                    $existing = DB::one('SELECT id FROM folders WHERE parent_id IS NULL AND LOWER(name)=LOWER(?) ORDER BY id LIMIT 1',[$row['category']]);
                    $categories[$categoryKey] = $existing?(int)$existing['id']:
                        DB::insert('INSERT INTO folders(parent_id,name,sort_position) VALUES (NULL,?,?)',
                            [$row['category'],(int)DB::one('SELECT COALESCE(MAX(sort_position),0)+1 AS position FROM folders WHERE parent_id IS NULL')['position']]);
                }
                $subcategoryKey = $categoryKey . "\0" . mb_strtolower($row['subcategory']);
                if (!isset($subcategories[$subcategoryKey])) {
                    $existing = DB::one('SELECT id FROM folders WHERE parent_id=? AND LOWER(name)=LOWER(?) ORDER BY id LIMIT 1',[$categories[$categoryKey],$row['subcategory']]);
                    $subcategories[$subcategoryKey] = $existing?(int)$existing['id']:
                        DB::insert('INSERT INTO folders(parent_id,name,sort_position) VALUES (?,?,?)',
                            [$categories[$categoryKey],$row['subcategory'],(int)DB::one('SELECT COALESCE(MAX(sort_position),0)+1 AS position FROM folders WHERE parent_id=?',[$categories[$categoryKey]])['position']]);
                }
                $type = DB::one('SELECT * FROM item_types WHERE folder_id=? AND LOWER(name)=LOWER(?) AND sku <=> ? ORDER BY id LIMIT 1 FOR UPDATE',
                    [$subcategories[$subcategoryKey],$row['name'],$row['sku']!==''?$row['sku']:null]);
                if (!$type) {
                    $prefix = self::catalogPrefix($row,$categories[$categoryKey],$subcategories[$subcategoryKey]);
                    $position = (int)DB::one('SELECT COALESCE(MAX(sort_position),0)+1 AS position FROM item_types WHERE folder_id=?',[$subcategories[$subcategoryKey]])['position'];
                    $typeId = DB::insert('INSERT INTO item_types(folder_id,name,prefix,sort_position,sku,unit,default_note,description) VALUES (?,?,?,?,?,?,?,?)',
                        [$subcategories[$subcategoryKey],$row['name'],$prefix,$position,$row['sku']!==''?$row['sku']:null,$row['unit'],$row['default_note']?:null,$row['description']?:null]);
                    $createdTypes++;
                    Audit::record('item_type.created','item_type',$typeId,['source'=>'catalog_import','sku'=>$row['sku']]);
                    $existingCount = 0;
                } else {
                    $typeId = (int)$type['id'];
                    $existingCount = (int)DB::one('SELECT COUNT(*) AS total FROM assets WHERE item_type_id=?',[$typeId])['total'];
                    DB::execute('UPDATE item_types SET sku=?,unit=?,default_note=?,description=? WHERE id=?',
                        [$row['sku']!==''?$row['sku']:null,$row['unit'],$row['default_note']?:null,$row['description']?:null,$typeId]);
                }
                $quantityToAdd = max(0,$row['shop_quantity']-$existingCount);
                if ($quantityToAdd>0) {
                    $addedAssets+=self::createCatalogAssets($typeId,$type['prefix'] ?? DB::one('SELECT prefix FROM item_types WHERE id=?',[$typeId])['prefix'],$quantityToAdd,$userId);
                }
                $typeResults[]=['id'=>$typeId,'name'=>$row['name'],'requested'=>$row['shop_quantity'],'existing'=>$existingCount,'added'=>$quantityToAdd];
            }
            DB::insert('INSERT INTO inventory_imports(content_hash,row_count,item_type_count,asset_count,created_by) VALUES (?,?,?,?,?)',
                [$contentHash,$parsed['row_count'],count($rows),$addedAssets,$userId]);
            Audit::record('inventory.catalog_imported','inventory',0,[
                'rows'=>$parsed['row_count'],'item_types'=>count($rows),'created_item_types'=>$createdTypes,
                'added_assets'=>$addedAssets,'source_hash'=>$contentHash,'items'=>$typeResults,
            ]);
            Documents::generate('inventory',0);
            return ['rows'=>$parsed['row_count'],'item_types'=>count($rows),'created_item_types'=>$createdTypes,'assets_added'=>$addedAssets];
        });
    }

    private static function catalogPrefix(array $row,int $categoryId,int $subcategoryId): string
    {
        $source = ($row['sku']!==''?$row['sku']:$row['category'] . '-' . $row['subcategory'] . '-' . $row['name']);
        $source = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$source) ?: $source;
        $stem = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/','-',$source),'-'));
        $stem = substr($stem ?: "ITEM-$categoryId-$subcategoryId",0,20);
        $candidate = $stem;
        $suffix = 1;
        while (DB::one('SELECT id FROM item_types WHERE prefix=? LIMIT 1',[$candidate])) {
            $tail = '-' . ++$suffix;
            $candidate = substr($stem,0,24-strlen($tail)) . $tail;
        }
        return $candidate;
    }

    private static function createCatalogAssets(int $typeId,string $prefix,int $quantity,int $userId): int
    {
        $existing = DB::all('SELECT asset_tag FROM assets WHERE item_type_id=? FOR UPDATE',[$typeId]);
        $tags = array_column($existing,'asset_tag');
        $next = 1;
        foreach ($tags as $tag) {
            if (preg_match('/^' . preg_quote($prefix,'/') . '-(\d+)$/D',$tag,$match)) {
                $next=max($next,(int)$match[1]+1);
            }
        }
        $created = 0;
        while ($created<$quantity) {
            $count=min(200,$quantity-$created);
            $batchTags=[];
            $values=[];
            $params=[];
            for ($i=0;$i<$count;$i++) {
                $tag=$prefix . '-' . str_pad((string)$next++,4,'0',STR_PAD_LEFT);
                if (in_array($tag,$tags,true)) {
                    throw new HttpException(409,"Generated serialized asset tag $tag already exists.");
                }
                $tags[]=$tag;
                $batchTags[]=$tag;
                $values[]='(?,?,NULL)';
                array_push($params,$typeId,$tag);
            }
            $placeholders=implode(',',array_fill(0,count($batchTags),'?'));
            if (DB::one("SELECT id FROM assets WHERE asset_tag IN ($placeholders) LIMIT 1",$batchTags)) {
                throw new HttpException(409,'One or more generated serialized asset tags already exist.');
            }
            DB::execute('INSERT INTO assets(item_type_id,asset_tag,serial_number) VALUES ' . implode(',',$values),$params);
            $assets=DB::all("SELECT id,asset_tag FROM assets WHERE asset_tag IN ($placeholders)",$batchTags);
            $eventRows=[];
            $eventParams=[];
            $auditRows=[];
            $auditParams=[];
            foreach ($assets as $asset) {
                $eventRows[]='(?,?,?,?)';
                array_push($eventParams,(int)$asset['id'],'Available',json_encode(['source'=>'catalog_import'],JSON_THROW_ON_ERROR),$userId);
                $auditRows[]='(?,?,?,?,?,?)';
                array_push($auditParams,$userId,'asset.created','asset',(int)$asset['id'],
                    json_encode(['item_type_id'=>$typeId,'source'=>'catalog_import'],JSON_THROW_ON_ERROR),
                    request()->ip() ?? '127.0.0.1');
            }
            DB::execute('INSERT INTO asset_events(asset_id,state,details,created_by) VALUES ' . implode(',',$eventRows),$eventParams);
            DB::execute('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,details,ip_address) VALUES ' . implode(',',$auditRows),$auditParams);
            $created+=$count;
        }
        return $created;
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
