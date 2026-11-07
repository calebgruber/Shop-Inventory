<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;

final class Rules
{
    public static function required(int $quantity, int $sourceRatio, int $dependencyRatio): int
    {
        return (int) ceil($quantity / $sourceRatio) * $dependencyRatio;
    }

    public static function validate(array $lines): array
    {
        $issues = [];
        $rules = DB::all('SELECT r.*,i.name AS dependency_name FROM equipment_rules r JOIN item_types i ON i.id=r.dependency_type_id WHERE r.active=1');
        $groups = [];
        foreach ($rules as $rule) {
            $groups[$rule['dependency_type_id']][] = $rule;
        }
        foreach ($groups as $dependencyType => $dependenciesRules) {
            $dates = [];
            foreach ($lines as $line) {
                $dates[] = $line['start_date'];
                $dates[] = date('Y-m-d',strtotime($line['return_date'] . ' UTC')+86400);
            }
            foreach (array_unique($dates) as $date) {
                $count = fn(int $type): int => array_sum(array_map(fn(array $l): int =>
                    (int)$l['item_type_id']===$type && $l['start_date'] <= $date && $l['return_date'] >= $date ? (int)$l['quantity'] : 0,$lines));
                $strict = $required = 0;
                foreach ($dependenciesRules as $rule) {
                    $quantity = self::required($count((int)$rule['source_type_id']),(int)$rule['source_quantity'],(int)$rule['dependency_quantity']);
                    $required += $quantity;
                    if ($rule['mode']==='strict') {
                        $strict += $quantity;
                    }
                }
                $provided = $count((int)$dependencyType);
                if ($provided < $required) {
                    $name = $dependenciesRules[0]['dependency_name'];
                    $mode = $provided<$strict?'strict':'warning';
                    $issues[] = ['mode'=>$mode,'type'=>'rule','message'=>"Accessory dependencies require $required $name on $date; requested $provided ($strict strictly required)."];
                    break;
                }
            }
        }
        return $issues;
    }
}
