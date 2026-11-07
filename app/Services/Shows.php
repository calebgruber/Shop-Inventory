<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;

final class Shows
{
    public const STATUSES = ['Planning','Prep Pending','In Prep','Checked Out','Running','Returning','Completed','Archived'];
    public const DATES = ['prep_start','prep_end','load_in_start','load_in_end','tech_start','tech_end','opening_night','strike_date','return_date'];
    public const CONTACTS = ['lighting_designer','assistant_lighting_designer','production_electrician'];

    public static function find(int $id, bool $lock = false): array
    {
        return DB::one('SELECT * FROM shows WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])
            ?? throw new HttpException(404, 'Show not found.');
    }

    public static function editable(array $show): void
    {
        if (in_array($show['status'], ['Completed','Archived'], true)) {
            throw new HttpException(422, 'Completed and archived shows cannot receive new equipment.');
        }
    }

    public static function save(array $data, ?int $id = null): int
    {
        $values = [
            'name'=>Validation::text($data['name'] ?? '', 'Show name'),
            'theatre'=>Validation::text($data['theatre'] ?? '', 'Theatre'),
            'status'=>Validation::choice($data['status'] ?? 'Planning',self::STATUSES,'show status'),
        ];
        foreach (self::CONTACTS as $contact) {
            $values[$contact] = Validation::text($data[$contact] ?? '', str_replace('_',' ',$contact),160,false);
            $email = Validation::text($data[$contact . '_email'] ?? '', 'Email',254,false);
            if ($email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL)) {
                throw new HttpException(422, 'Enter valid contact emails.');
            }
            $values[$contact . '_email'] = $email ?: null;
            $values[$contact . '_phone'] = Validation::text($data[$contact . '_phone'] ?? '', 'Phone',40,false) ?: null;
        }
        $last = null;
        foreach (self::DATES as $date) {
            $values[$date] = Validation::date($data[$date] ?? '', str_replace('_',' ',$date));
            if ($last !== null && $values[$date] < $last) {
                throw new HttpException(422, 'Show dates must follow production chronology.');
            }
            $last = $values[$date];
        }
        return DB::transaction(function () use ($values,$id): int {
            if ($id) {
                $before = self::find($id,true);
                foreach (self::DATES as $date) {
                    if ($before[$date] !== $values[$date] && DB::one('SELECT id FROM shop_orders WHERE show_id=? LIMIT 1',[$id])) {
                        throw new HttpException(422, 'Order dates are historical snapshots. Schedule changes after orders exist require a new revision with explicit dates.');
                    }
                }
                if (in_array($values['status'],['Completed','Archived'],true) &&
                    DB::one('SELECT id FROM assignments WHERE show_id=? AND ended_at IS NULL LIMIT 1',[$id])) {
                    throw new HttpException(422, 'Resolve outstanding equipment before completing or archiving the show.');
                }
                if ($before['status'] === 'Archived') {
                    throw new HttpException(422, 'Archived shows are read only.');
                }
                $set = implode(',',array_map(fn(string $key): string => "$key=?",array_keys($values)));
                DB::execute("UPDATE shows SET $set WHERE id=?", [...array_values($values),$id]);
                Audit::record('show.updated','show',$id,['before'=>$before,'after'=>$values]);
            } else {
                if ($values['status'] !== 'Planning') {
                    throw new HttpException(422, 'New shows begin in Planning.');
                }
                $keys = implode(',',array_keys($values));
                $marks = implode(',',array_fill(0,count($values),'?'));
                $id = DB::insert("INSERT INTO shows($keys,created_by) VALUES ($marks,?)",[...array_values($values),Auth::user()['id']]);
                Audit::record('show.created','show',$id);
            }
            if (in_array($values['status'],['Checked Out','Running'],true)) {
                foreach (DB::all('SELECT asset_id FROM assignments WHERE show_id=? AND ended_at IS NULL',[$id]) as $asset) {
                    DB::execute("INSERT INTO asset_events(asset_id,show_id,state,details,created_by) VALUES (?,?,'On Show',?,?)",
                        [$asset['asset_id'],$id,json_encode(['show_status'=>$values['status']],JSON_THROW_ON_ERROR),Auth::user()['id']]);
                }
            }
            Documents::generate($values['status']==='Archived'?'archive':'show', $id);
            return $id;
        });
    }
}
