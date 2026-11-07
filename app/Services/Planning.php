<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;

final class Planning
{
    public static function peak(array $intervals, string $start, string $end): int
    {
        $events = [];
        $from = strtotime($start . ' UTC');
        $until = strtotime($end . ' UTC');
        foreach ($intervals as $interval) {
            $a = max($from,strtotime($interval['start_date'] . ' UTC'));
            $b = min($until,strtotime($interval['return_date'] . ' UTC'));
            if ($a > $b || $interval['quantity'] <= 0) {
                continue;
            }
            $events[$a] = ($events[$a] ?? 0) + (int) $interval['quantity'];
            $events[$b + 86400] = ($events[$b + 86400] ?? 0) - (int) $interval['quantity'];
        }
        ksort($events);
        $running = $peak = 0;
        foreach ($events as $change) {
            $running += $change;
            $peak = max($peak,$running);
        }
        return $peak;
    }

    public static function commitments(int $typeId, ?int $excludeRevision = null): array
    {
        return DB::all("SELECT l.start_date,
            CASE WHEN EXISTS(SELECT 1 FROM assignments od WHERE od.line_id=l.id AND od.ended_at IS NULL AND od.return_date<CURRENT_DATE())
                THEN '9999-12-31' ELSE l.return_date END AS return_date,
            GREATEST(0,l.quantity-(SELECT COUNT(*) FROM assignments a WHERE a.line_id=l.id AND a.ended_at IS NOT NULL)) AS quantity,
            s.name AS show_name,s.id AS show_id,l.id AS line_id
            FROM revision_lines l JOIN revisions r ON r.id=l.revision_id
            JOIN revision_approvals ap ON ap.revision_id=r.id JOIN shop_orders o ON o.id=r.order_id JOIN shows s ON s.id=o.show_id
            WHERE l.item_type_id=? AND ap.status<>'Rejected' AND (? IS NULL OR r.id<>?)",
            [$typeId,$excludeRevision,$excludeRevision]);
    }

    public static function availability(int $typeId, string $start, string $end, ?int $excludeRevision = null): array
    {
        $totals = DB::one("SELECT COUNT(*) AS total,SUM(state IN ('Available','On Show')) AS usable FROM assets WHERE item_type_id=?",[$typeId]);
        $committed = self::peak(self::commitments($typeId,$excludeRevision),$start,$end);
        return ['total'=>(int)$totals['total'],'usable'=>(int)$totals['usable'],'committed'=>$committed,
            'available'=>max(0,(int)$totals['usable']-$committed),'shortage'=>max(0,$committed-(int)$totals['usable'])];
    }

    public static function validate(array $lines, ?int $excludeRevision = null): array
    {
        $issues = Rules::validate($lines);
        $byType = [];
        foreach ($lines as $line) {
            if (isset($line['returned'])) {
                $line['quantity'] = max(0,(int)$line['quantity']-(int)$line['returned']);
            }
            $byType[$line['item_type_id']][] = $line;
        }
        foreach ($byType as $typeId=>$requests) {
            $start = min(array_column($requests,'start_date'));
            $end = max(array_column($requests,'return_date'));
            $available = self::availability((int)$typeId,$start,$end,$excludeRevision);
            $peak = self::peak([...self::commitments((int)$typeId,$excludeRevision),...$requests],$start,$end);
            if ($peak > $available['usable']) {
                $name = DB::one('SELECT name FROM item_types WHERE id=?',[$typeId])['name'];
                $issues[] = ['mode'=>'strict','type'=>'shortage','message'=>"$name: peak demand $peak exceeds {$available['usable']} serviceable assets; shortage " . ($peak-$available['usable']) . '.'];
            }
        }
        return $issues;
    }
}
