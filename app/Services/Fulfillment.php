<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;

final class Fulfillment
{
    private static function event(int $assetId, int $showId, int $operationId, string $state, array $details = []): void
    {
        DB::execute('INSERT INTO asset_events(asset_id,show_id,operation_id,state,details,created_by) VALUES (?,?,?,?,?,?)',
            [$assetId,$showId,$operationId,$state,json_encode($details,JSON_THROW_ON_ERROR),Auth::user()['id']]);
    }

    private static function operation(int $showId, ?int $revisionId, string $type, string $mode): int
    {
        return DB::insert('INSERT INTO operations(show_id,revision_id,type,mode,created_by) VALUES (?,?,?,?,?)',
            [$showId,$revisionId,$type,$mode,Auth::user()['id']]);
    }

    public static function pull(int $revisionId, array $data): array
    {
        return DB::transaction(function () use ($revisionId,$data): array {
            $revision = Orders::revision($revisionId);
            $show = Shows::find((int)$revision['show_id'],true);
            Shows::editable($show);
            if ($revision['status']!=='Approved') {
                throw new HttpException(422,'Only approved orders and revisions can be pulled.');
            }
            $lines = Orders::lines($revisionId);
            Orders::lockTypes($lines);
            if ($show['prep_start']>gmdate('Y-m-d')) {
                throw new HttpException(422,'The show prep period has not started.');
            }
            $mode = Settings::get('pull_mode','barcode');
            $selection = [];
            if ($mode==='barcode') {
                $tag = Validation::text($data['asset_tag'] ?? '', 'Asset barcode',80);
                $asset = DB::one('SELECT * FROM assets WHERE asset_tag=? FOR UPDATE',[$tag]);
                if (!$asset || $asset['state']!=='Available') {
                    throw new HttpException(422,'Asset is unknown, already scanned, checked out, missing, or in repair.');
                }
                foreach ($lines as $line) {
                    if ((int)$line['item_type_id']===(int)$asset['item_type_id'] && (int)$line['pulled']<(int)$line['quantity'] && $line['start_date']<=gmdate('Y-m-d') && $line['return_date']>=gmdate('Y-m-d')) {
                        $selection[] = [$asset,$line];
                        break;
                    }
                }
                if (!$selection) {
                    throw new HttpException(422,'Incorrect asset, exceeded quantity, or equipment is outside its requested dates.');
                }
            } else {
                $quantities = $data['quantities'] ?? [];
                if (!is_array($quantities)) {
                    throw new HttpException(422,'Invalid manual quantities.');
                }
                foreach ($lines as $line) {
                    $raw = $quantities[$line['id']] ?? 0;
                    if ((string)$raw==='0' || (string)$raw==='') {
                        continue;
                    }
                    $quantity = Validation::positive($raw,'Pull quantity');
                    if ($quantity>(int)$line['quantity']-(int)$line['pulled']) {
                        throw new HttpException(422,'Manual pulls cannot exceed the remaining requested quantity.');
                    }
                    if ($line['start_date']>gmdate('Y-m-d') || $line['return_date']<gmdate('Y-m-d')) {
                        throw new HttpException(422,'Equipment is outside its requested dates.');
                    }
                    $used = array_column(array_column($selection,0),'id');
                    $exclude = $used?' AND id NOT IN (' . implode(',',array_fill(0,count($used),'?')) . ')':'';
                    $assets = DB::all("SELECT * FROM assets WHERE item_type_id=? AND state='Available'$exclude ORDER BY id LIMIT $quantity FOR UPDATE",[$line['item_type_id'],...$used]);
                    if (count($assets)!==$quantity) {
                        throw new HttpException(422,'Not enough unassigned, serviceable assets to fulfill this manual pull.');
                    }
                    foreach ($assets as $asset) {
                        $selection[] = [$asset,$line];
                    }
                }
            }
            if (!$selection) {
                throw new HttpException(422,'Enter at least one quantity or scan an asset.');
            }
            $issues = Planning::validate($lines,$revisionId);
            if (array_filter($issues,fn(array $i): bool => $i['mode']==='strict')) {
                throw new HttpException(422,implode(' ',array_column($issues,'message')));
            }
            $operationId = self::operation((int)$show['id'],$revisionId,'Pull',$mode);
            foreach ($selection as [$asset,$line]) {
                DB::execute('INSERT INTO assignments(asset_id,show_id,line_id,operation_id,start_date,return_date) VALUES (?,?,?,?,?,?)',
                    [$asset['id'],$show['id'],$line['id'],$operationId,$line['start_date'],$line['return_date']]);
                DB::execute("UPDATE assets SET state='On Show' WHERE id=?",[$asset['id']]);
                self::event((int)$asset['id'],(int)$show['id'],$operationId,$revision['revision_number']>0?'Revision Pull':'Assigned to Show',['revision_id'=>$revisionId,'return_date'=>$line['return_date']]);
            }
            DB::execute("UPDATE shows SET status='In Prep' WHERE id=? AND status IN ('Planning','Prep Pending')",[$show['id']]);
            $updated = Orders::lines($revisionId);
            $partial = array_sum(array_column($updated,'pulled'))<array_sum(array_column($updated,'quantity'));
            Audit::record('pull.created','show',(int)$show['id'],['operation_id'=>$operationId,'revision_id'=>$revisionId,'quantity'=>count($selection),'partial'=>$partial]);
            Documents::generate('operation',$operationId);
            return ['operation_id'=>$operationId,'partial'=>$partial,'lines'=>$updated];
        });
    }

    public static function returns(int $showId, array $data): array
    {
        return DB::transaction(function () use ($showId,$data): array {
            Shows::find($showId,true);
            $condition = Validation::choice($data['condition'] ?? 'Good',['Good','Broken','Missing Parts','Missing'],'return condition');
            $description = Validation::text($data['description'] ?? '', 'Condition notes',10000,$condition!=='Good');
            $active = DB::all('SELECT a.*,s.asset_tag,s.item_type_id FROM assignments a JOIN assets s ON s.id=a.asset_id WHERE a.show_id=? AND a.ended_at IS NULL ORDER BY a.return_date,a.id',[$showId]);
            Orders::lockTypes(array_map(fn(array $a): array => ['item_type_id'=>$a['item_type_id']],$active));
            $mode = Settings::get('return_mode','barcode');
            $selection = [];
            if ($mode==='barcode') {
                $tag = Validation::text($data['asset_tag'] ?? '', 'Return barcode',80);
                $selection = array_values(array_filter($active,fn(array $a): bool => $a['asset_tag']===$tag));
            } else {
                $quantities = $data['quantities'] ?? [];
                if (!is_array($quantities)) {
                    throw new HttpException(422,'Invalid return quantities.');
                }
                foreach ($quantities as $type=>$raw) {
                    if ((string)$raw==='0' || (string)$raw==='') {
                        continue;
                    }
                    $type = Validation::positive($type,'Item type');
                    $quantity = Validation::positive($raw,'Return quantity');
                    $candidates = array_values(array_filter($active,fn(array $a): bool => (int)$a['item_type_id']===$type));
                    if ($quantity>count($candidates)) {
                        throw new HttpException(422,'Return quantities exceed equipment on this show.');
                    }
                    $selection = [...$selection,...array_slice($candidates,0,$quantity)];
                }
            }
            if (!$selection) {
                throw new HttpException(422,'Asset is not on this show, has already been returned, or no quantity was entered.');
            }
            $operationId = self::operation($showId,null,'Return',$mode);
            foreach ($selection as $assignment) {
                DB::one('SELECT id FROM assets WHERE id=? FOR UPDATE',[$assignment['asset_id']]);
                DB::execute("UPDATE assignments SET ended_at=UTC_TIMESTAMP(),end_reason='Return',condition_on_return=? WHERE id=? AND ended_at IS NULL",[$condition,$assignment['id']]);
                $state = match ($condition) {'Good'=>'Available','Missing'=>'Missing',default=>'Repair'};
                DB::execute('UPDATE assets SET state=? WHERE id=?',[$state,$assignment['asset_id']]);
                self::event((int)$assignment['asset_id'],$showId,$operationId,'Return',['condition'=>$condition,'description'=>$description,'assignment_id'=>$assignment['id']]);
                if ($state==='Repair') {
                    $repairId = DB::insert('INSERT INTO repairs(asset_id,show_id,description,created_by) VALUES (?,?,?,?)',
                        [$assignment['asset_id'],$showId,$description,Auth::user()['id']]);
                    self::event((int)$assignment['asset_id'],$showId,$operationId,'Repair',['repair_id'=>$repairId]);
                    Audit::record('repair.created','repair',$repairId);
                    Documents::generate('repair',$repairId);
                }
            }
            DB::execute("UPDATE shows SET status='Returning' WHERE id=? AND status<>'Archived'",[$showId]);
            Audit::record('return.created','show',$showId,['operation_id'=>$operationId,'condition'=>$condition,'quantity'=>count($selection)]);
            Documents::generate('operation',$operationId);
            $remaining = DB::all('SELECT s.item_type_id,COUNT(*) AS quantity FROM assignments a JOIN assets s ON s.id=a.asset_id WHERE a.show_id=? AND a.ended_at IS NULL GROUP BY s.item_type_id',[$showId]);
            return ['operation_id'=>$operationId,'quantity'=>count($selection),'remaining'=>$remaining];
        });
    }

    public static function transfer(int $sourceShow, array $data): int
    {
        $destinationLine = Validation::positive($data['destination_line_id'] ?? 0,'Destination approved order line');
        $tags = $data['asset_tags'] ?? [];
        if (!is_array($tags) || !$tags || count($tags)>1000 || count(array_unique($tags))!==count($tags)) {
            throw new HttpException(422,'Provide a non-empty list of unique asset barcodes.');
        }
        return DB::transaction(function () use ($sourceShow,$destinationLine,$tags): int {
            $line = DB::one("SELECT l.*,o.show_id,ap.status,r.id AS revision_id FROM revision_lines l JOIN revisions r ON r.id=l.revision_id
                JOIN shop_orders o ON o.id=r.order_id JOIN revision_approvals ap ON ap.revision_id=r.id WHERE l.id=?",[$destinationLine]);
            if (!$line || !in_array($line['status'],['Pending','Approved'],true) || (int)$line['show_id']===$sourceShow) {
                throw new HttpException(422,'Select a pending or approved destination order on a different show.');
            }
            $ids = [$sourceShow,(int)$line['show_id']];
            sort($ids,SORT_NUMERIC);
            foreach ($ids as $showId) {
                Shows::editable(Shows::find($showId,true));
            }
            Orders::lockTypes([$line]);
            $approval = DB::one('SELECT * FROM revision_approvals WHERE revision_id=? FOR UPDATE',[$line['revision_id']]);
            if ($approval['status']==='Rejected') {
                throw new HttpException(422,'The destination request has been rejected.');
            }
            if ($line['start_date']>gmdate('Y-m-d') || $line['return_date']<gmdate('Y-m-d')) {
                throw new HttpException(422,'The destination request is outside its approved dates.');
            }
            $pulled = (int)DB::one('SELECT COUNT(*) AS total FROM assignments WHERE line_id=?',[$destinationLine])['total'];
            if (count($tags)>$line['quantity']-$pulled) {
                throw new HttpException(422,'Transfer exceeds the destination requested quantity.');
            }
            $operationId = self::operation((int)$line['show_id'],(int)$line['revision_id'],'Transfer','barcode');
            foreach ($tags as $tag) {
                $tag = Validation::text($tag,'Barcode',80);
                $assignment = DB::one('SELECT a.*,s.item_type_id FROM assignments a JOIN assets s ON s.id=a.asset_id WHERE a.show_id=? AND a.ended_at IS NULL AND s.asset_tag=? FOR UPDATE',[$sourceShow,$tag]);
                if (!$assignment || (int)$assignment['item_type_id']!==(int)$line['item_type_id']) {
                    throw new HttpException(422,'A transferred asset is not on the source show or has the wrong item type.');
                }
                DB::execute("UPDATE assignments SET ended_at=UTC_TIMESTAMP(),end_reason='Transfer' WHERE id=?",[$assignment['id']]);
                DB::execute('INSERT INTO assignments(asset_id,show_id,line_id,operation_id,start_date,return_date) VALUES (?,?,?,?,?,?)',
                    [$assignment['asset_id'],$line['show_id'],$destinationLine,$operationId,$line['start_date'],$line['return_date']]);
                self::event((int)$assignment['asset_id'],(int)$line['show_id'],$operationId,'Transfer',['from_show'=>$sourceShow,'to_show'=>$line['show_id'],'from_assignment'=>$assignment['id']]);
            }
            $issues = Planning::validate(Orders::lines((int)$line['revision_id']),(int)$line['revision_id']);
            if (array_filter($issues,fn(array $i): bool => $i['mode']==='strict')) {
                throw new HttpException(422,implode(' ',array_column($issues,'message')));
            }
            if ($approval['status']==='Pending') {
                \App\Middleware\Permissions::require('orders.approve');
                DB::execute("UPDATE revision_approvals SET status='Approved',approved_by=?,approved_at=UTC_TIMESTAMP() WHERE revision_id=?", [Auth::user()['id'],$line['revision_id']]);
                Audit::record('revision.approved_by_transfer','revision',(int)$line['revision_id'],['operation_id'=>$operationId]);
                Documents::generate('revision',(int)$line['revision_id']);
            }
            Audit::record('transfer.sent','show',$sourceShow,['operation_id'=>$operationId,'destination'=>$line['show_id'],'quantity'=>count($tags)]);
            Audit::record('transfer.received','show',(int)$line['show_id'],['operation_id'=>$operationId,'source'=>$sourceShow,'quantity'=>count($tags)]);
            DB::execute("UPDATE shows SET status='Checked Out' WHERE id=? AND status IN ('Planning','Prep Pending','In Prep')",[$line['show_id']]);
            Documents::generate('operation',$operationId);
            return $operationId;
        });
    }

    public static function repair(int $id, array $data): void
    {
        $status = Validation::choice($data['status'] ?? '',['Open','Working','Waiting Parts','Completed'],'repair status');
        $notes = Validation::text($data['notes'] ?? '', 'Repair update notes',10000);
        DB::transaction(function () use ($id,$status,$notes): void {
            $repair = DB::one('SELECT * FROM repairs WHERE id=? FOR UPDATE',[$id]) ?? throw new HttpException(404,'Repair not found.');
            if ($repair['status']==='Completed') {
                throw new HttpException(422,'Completed repair tickets are historical. Log a new repair for a new fault.');
            }
            $asset = DB::one('SELECT * FROM assets WHERE id=?',[$repair['asset_id']]);
            Orders::lockTypes([['item_type_id'=>$asset['item_type_id']]]);
            if ($status==='Completed') {
                DB::execute("UPDATE repairs SET status=?,completed_by=?,completed_at=UTC_TIMESTAMP() WHERE id=?",[$status,Auth::user()['id'],$id]);
                if (!DB::one("SELECT id FROM repairs WHERE asset_id=? AND status<>'Completed' LIMIT 1",[$repair['asset_id']])) {
                    DB::execute("UPDATE assets SET state='Available' WHERE id=?",[$repair['asset_id']]);
                }
            } else {
                DB::execute('UPDATE repairs SET status=? WHERE id=?',[$status,$id]);
            }
            Audit::record('repair.updated','repair',$id,['before'=>$repair['status'],'after'=>$status,'notes'=>$notes]);
            if ($repair['show_id']) {
                Audit::record('repair.updated','show',(int)$repair['show_id'],['repair_id'=>$id,'status'=>$status]);
            }
            DB::execute('INSERT INTO asset_events(asset_id,show_id,state,details,created_by) VALUES (?,?,?,?,?)',
                [$repair['asset_id'],$repair['show_id'],$status==='Completed'?'Available':'Repair',
                    json_encode(['repair_id'=>$id,'status'=>$status,'notes'=>$notes],JSON_THROW_ON_ERROR),Auth::user()['id']]);
            Documents::generate('repair',$id);
        });
    }
}
