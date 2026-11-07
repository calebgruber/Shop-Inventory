<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\Database as DB;
use App\Services\Orders;
use App\Services\Shows;
use App\Services\Settings;
use App\Services\Fulfillment;
use App\Services\Validation;
use App\Services\HttpException;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\Documents;
use App\Services\Uploads;

final class OperationsController
{
    public function pull(int $id): void
    {
        $revision = Orders::revision($id);
        $show = Shows::find((int)$revision['show_id']);
        $lines = Orders::lines($id);
        $mode = Settings::get('pull_mode','barcode');
        \view('operations/pull',compact('revision','show','lines','mode')+['title'=>'Pull ' . $revision['number'],'workspaceKey'=>'pull:' . $id]);
    }

    public function pullSave(int $id): void
    {
        \json_response(Fulfillment::pull($id,\input())+['message'=>'Pull recorded.']);
    }

    public function returns(int $id): void
    {
        $show = Shows::find($id);
        $lines = DB::all('SELECT i.id,i.name,COUNT(a.id) AS quantity FROM assignments a JOIN assets s ON s.id=a.asset_id JOIN item_types i ON i.id=s.item_type_id WHERE a.show_id=? AND a.ended_at IS NULL GROUP BY i.id,i.name ORDER BY i.name',[$id]);
        $mode = Settings::get('return_mode','barcode');
        \view('operations/returns',compact('show','lines','mode')+['title'=>'Return ' . $show['name'],'workspaceKey'=>'return:' . $id]);
    }

    public function returnSave(int $id): void
    {
        \json_response(Fulfillment::returns($id,\input())+['message'=>'Return recorded.']);
    }

    public function transfer(int $id): void
    {
        $show = Shows::find($id);
        $destinations = DB::all("SELECT l.id,i.name AS equipment,s.name AS show_name,o.number,r.revision_number,l.quantity,l.return_date,
            (SELECT COUNT(*) FROM assignments a WHERE a.line_id=l.id) AS pulled
            FROM revision_lines l JOIN item_types i ON i.id=l.item_type_id JOIN revisions r ON r.id=l.revision_id JOIN revision_approvals ap ON ap.revision_id=r.id
            JOIN shop_orders o ON o.id=r.order_id JOIN shows s ON s.id=o.show_id WHERE ap.status IN ('Approved','Pending') AND s.id<>? AND s.status NOT IN ('Completed','Archived') ORDER BY s.name",[$id]);
        \view('operations/transfer',compact('show','destinations')+['title'=>'Transfer from ' . $show['name'],'workspaceKey'=>'show:' . $id,'workspaceLabel'=>$show['name']]);
    }

    public function transferSave(int $id): void
    {
        $data = \input();
        $data['asset_tags'] = preg_split('/\r\n|\r|\n/',trim(Validation::text($data['asset_tags_text'] ?? '', 'Asset list',100000)));
        $operation = Fulfillment::transfer($id,$data);
        \json_response(['redirect'=>'/operations/' . $operation]);
    }

    public function repair(int $id): void
    {
        $repair = DB::one('SELECT r.*,a.asset_tag,i.name AS item_name,s.name AS show_name FROM repairs r JOIN assets a ON a.id=r.asset_id JOIN item_types i ON i.id=a.item_type_id LEFT JOIN shows s ON s.id=r.show_id WHERE r.id=?',[$id])
            ?? throw new HttpException(404,'Repair not found.');
        $history = DB::all("SELECT action,details,created_at FROM audit_logs WHERE entity_type='repair' AND entity_id=? ORDER BY id",[$id]);
        \view('operations/repair',compact('repair','history')+['title'=>'Repair #' . $id,'workspaceKey'=>'repair:' . $id]);
    }

    public function repairSave(int $id): void
    {
        Fulfillment::repair($id,\input());
        \json_response(['redirect'=>'/repairs/' . $id]);
    }

    public function operation(int $id): void
    {
        $operation = DB::one('SELECT o.*,s.name AS show_name FROM operations o JOIN shows s ON s.id=o.show_id WHERE o.id=?',[$id]) ?? throw new HttpException(404,'Operation not found.');
        $events = DB::all('SELECT a.asset_tag,e.state,e.details,e.created_at FROM asset_events e JOIN assets a ON a.id=e.asset_id WHERE e.operation_id=? ORDER BY e.id',[$id]);
        \view('operations/detail',compact('operation','events')+['title'=>$operation['type'] . ' #' . $id,'workspaceKey'=>'operation:' . $id]);
    }

    public function note(int $id): void
    {
        $data = \input();
        DB::transaction(function () use ($id,$data): void {
            Shows::editable(Shows::find($id,true));
            $note = DB::insert('INSERT INTO show_notes(show_id,body,created_by) VALUES (?,?,?)',[$id,Validation::text($data['body'] ?? '', 'Note',10000),Auth::user()['id']]);
            Audit::record('note.added','show',$id,['note_id'=>$note]);
            Documents::generate('show',$id);
        });
        \json_response(['redirect'=>'/shows/' . $id . '/notes']);
    }

    public function file(int $id): void
    {
        Uploads::showFile($id,$_FILES['file'] ?? []);
        \json_response(['redirect'=>'/shows/' . $id . '/files']);
    }
}
