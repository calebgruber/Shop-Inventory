<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;

final class Orders
{
    public static function revision(int $id): array
    {
        return DB::one('SELECT r.*,o.show_id,o.number,o.title,ap.status,u.name AS created_by_name FROM revisions r JOIN shop_orders o ON o.id=r.order_id JOIN users u ON u.id=r.created_by JOIN revision_approvals ap ON ap.revision_id=r.id WHERE r.id=?',[$id])
            ?? throw new HttpException(404,'Revision not found.');
    }

    public static function lines(int $id): array
    {
        return DB::all('SELECT l.*,i.name AS item_name,p.name AS package_name,
            (SELECT COUNT(*) FROM assignments a WHERE a.line_id=l.id) AS pulled,
            (SELECT COUNT(*) FROM assignments a WHERE a.line_id=l.id AND a.ended_at IS NOT NULL) AS returned
            FROM revision_lines l JOIN item_types i ON i.id=l.item_type_id LEFT JOIN packages p ON p.id=l.package_id WHERE l.revision_id=? ORDER BY l.id',[$id]);
    }

    public static function expand(array $requests, array $show): array
    {
        if (!$requests || count($requests)>1000) {
            throw new HttpException(422,'An order needs between 1 and 1000 item or package lines.');
        }
        $lines = [];
        foreach ($requests as $request) {
            if (!is_array($request)) {
                throw new HttpException(422,'Invalid order line.');
            }
            $quantity = Validation::positive($request['quantity'] ?? 0,'Quantity');
            $start = Validation::date($request['start_date'] ?? $show['prep_start'],'Prep start');
            $end = Validation::date($request['return_date'] ?? $show['return_date'],'Return date');
            if ($end<$start) {
                throw new HttpException(422,'Return dates must not precede prep.');
            }
            $packageId = empty($request['package_id']) ? null : Validation::positive($request['package_id'],'Package');
            if ($packageId) {
                $components = DB::all('SELECT * FROM package_components WHERE package_id=?',[$packageId]);
                if (!$components) {
                    throw new HttpException(422,'The requested package has no components.');
                }
                foreach ($components as $component) {
                    $componentQuantity = $quantity*(int)$component['quantity'];
                    if ($componentQuantity>1000000) {
                        throw new HttpException(422,'Expanded package quantity is too large.');
                    }
                    $override = $request['item_return_dates'][$component['item_type_id']] ?? $end;
                    $return = Validation::date($override,'Component return date');
                    if ($return<$start) {
                        throw new HttpException(422,'Component return date precedes prep.');
                    }
                    $lines[] = ['item_type_id'=>(int)$component['item_type_id'],'quantity'=>$componentQuantity,
                        'package_id'=>$packageId,'start_date'=>$start,'return_date'=>$return];
                }
            } else {
                $typeId = Validation::positive($request['item_type_id'] ?? 0,'Item type');
                if (!DB::one('SELECT id FROM item_types WHERE id=?',[$typeId])) {
                    throw new HttpException(422,'Item type not found.');
                }
                $lines[] = ['item_type_id'=>$typeId,'quantity'=>$quantity,'package_id'=>null,'start_date'=>$start,'return_date'=>$end];
            }
        }
        return $lines;
    }

    public static function lockTypes(array $lines): void
    {
        $ids = array_unique(array_map(fn(array $l): int => (int)$l['item_type_id'],$lines));
        sort($ids,SORT_NUMERIC);
        foreach ($ids as $id) {
            DB::one('SELECT id FROM item_types WHERE id=? FOR UPDATE',[$id]);
        }
    }

    public static function create(int $showId, array $data, ?int $orderId = null): array
    {
        return DB::transaction(function () use ($showId,$data,$orderId): array {
            $show = Shows::find($showId,true);
            Shows::editable($show);
            if (!is_array($data['lines'] ?? null)) {
                throw new HttpException(422,'Provide order lines.');
            }
            $lines = self::expand($data['lines'],$show);
            self::lockTypes($lines);
            $issues = Planning::validate($lines);
            // Shortages remain visible for planning; approval/pull enforces them.
            if (array_filter($issues,fn(array $i): bool => $i['mode']==='strict' && $i['type']==='rule')) {
                throw new HttpException(422,implode(' ',array_column($issues,'message')));
            }
            if ($orderId) {
                $order = DB::one('SELECT * FROM shop_orders WHERE id=? AND show_id=? FOR UPDATE',[$orderId,$showId]);
                if (!$order) {
                    throw new HttpException(404,'Shop order not found for this show.');
                }
                $number = (int)DB::one('SELECT MAX(revision_number) AS number FROM revisions WHERE order_id=?',[$orderId])['number']+1;
            } else {
                $number = 0;
                $orderId = DB::insert('INSERT INTO shop_orders(show_id,number,title,created_by) VALUES (?,?,?,?)',
                    [$showId,'SO-' . strtoupper(bin2hex(random_bytes(6))),Validation::text($data['title'] ?? 'Shop order','Order title'),Auth::user()['id']]);
            }
            $revisionId = DB::insert('INSERT INTO revisions(order_id,revision_number,notes,created_by) VALUES (?,?,?,?)',
                [$orderId,$number,Validation::text($data['notes'] ?? '','Revision notes',10000,false),Auth::user()['id']]);
            foreach ($lines as $line) {
                DB::execute('INSERT INTO revision_lines(revision_id,item_type_id,quantity,package_id,start_date,return_date) VALUES (?,?,?,?,?,?)',
                    [$revisionId,$line['item_type_id'],$line['quantity'],$line['package_id'],$line['start_date'],$line['return_date']]);
            }
            DB::execute('INSERT INTO revision_approvals(revision_id) VALUES (?)',[$revisionId]);
            Audit::record($number===0?'order.created':'revision.created','show',$showId,['order_id'=>$orderId,'revision_id'=>$revisionId,'revision_number'=>$number]);
            Audit::record('revision.created','revision',$revisionId,['validation'=>$issues]);
            Documents::generate('revision',$revisionId);
            return ['order_id'=>$orderId,'revision_id'=>$revisionId,'issues'=>$issues];
        });
    }

    public static function approve(int $id, string $status): void
    {
        Validation::choice($status,['Approved','Rejected'],'approval status');
        DB::transaction(function () use ($id,$status): void {
            $revision = self::revision($id);
            Shows::editable(Shows::find((int)$revision['show_id'],true));
            $approval = DB::one('SELECT * FROM revision_approvals WHERE revision_id=? FOR UPDATE',[$id]);
            if ($approval['status']!=='Pending') {
                throw new HttpException(422,'An approval decision is final. Create a new revision rather than altering history.');
            }
            $lines = self::lines($id);
            self::lockTypes($lines);
            $issues = Planning::validate($lines,$id);
            if ($status==='Approved' && array_filter($issues,fn(array $i): bool => $i['mode']==='strict')) {
                throw new HttpException(422,implode(' ',array_column($issues,'message')));
            }
            DB::execute('UPDATE revision_approvals SET status=?,approved_by=?,approved_at=UTC_TIMESTAMP() WHERE revision_id=?',[$status,Auth::user()['id'],$id]);
            Audit::record('revision.' . strtolower($status),'revision',$id,['validation'=>$issues]);
            Audit::record('revision.' . strtolower($status),'show',(int)$revision['show_id'],['revision_id'=>$id]);
            Documents::generate('revision',$id);
        });
    }
}
