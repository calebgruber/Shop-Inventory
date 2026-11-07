<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;
use Dompdf\Dompdf;
use Dompdf\Options;

final class Documents
{
    private static array $pending = [];
    public const REPORTS = ['inventory','missing','utilization','timeline','repairs','packages','rules'];

    public static function commitPending(): void
    {
        self::$pending = [];
    }

    public static function rollbackPending(): void
    {
        foreach (self::$pending as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        self::$pending = [];
    }

    public static function section(string $title, array $rows): array
    {
        return ['title'=>$title,'rows'=>$rows];
    }

    public static function sections(string $type, int $id, array $filters = []): array
    {
        if ($type==='revision') {
            $revision = Orders::revision($id);
            $show = Shows::find((int)$revision['show_id']);
            return [
                self::section($revision['number'] . ' / ' . ($revision['revision_number']===0?'Original Shop Order':'Revision ' . $revision['revision_number']),
                    [['Show'=>$show['name'],'Theatre'=>$show['theatre'],'Approval'=>$revision['status'],'Created'=>$revision['created_at'],'Submitted_By'=>$revision['created_by_name'],'Notes'=>$revision['notes']]]),
                self::section('Equipment request',DB::all('SELECT i.name AS Equipment,l.quantity AS Quantity,p.name AS Package,l.start_date AS Prep,l.return_date AS Return_Date FROM revision_lines l JOIN item_types i ON i.id=l.item_type_id LEFT JOIN packages p ON p.id=l.package_id WHERE l.revision_id=?',[$id])),
            ];
        }
        if ($type==='show' || $type==='archive') {
            $show = Shows::find($id);
            $sections = [
                self::section('Show information',[array_diff_key($show,array_flip(['id','created_by','updated_at']))]),
                self::section('Notes',DB::all('SELECT n.body AS Note,u.name AS Author,n.created_at AS Created FROM show_notes n JOIN users u ON u.id=n.created_by WHERE n.show_id=? ORDER BY n.id',[$id])),
                self::section('Files manifest',DB::all('SELECT original_name AS File,mime AS Type,size_bytes AS Bytes,created_at AS Uploaded FROM files WHERE show_id=?',[$id])),
            ];
            foreach (DB::all('SELECT r.id FROM revisions r JOIN shop_orders o ON o.id=r.order_id WHERE o.show_id=? ORDER BY o.id,r.revision_number',[$id]) as $revision) {
                $sections = [...$sections,...self::sections('revision',(int)$revision['id'])];
            }
            if ($type==='archive') {
                foreach (DB::all("SELECT DISTINCT op.id FROM operations op LEFT JOIN asset_events e ON e.operation_id=op.id WHERE op.show_id=? OR e.show_id=? OR JSON_EXTRACT(e.details,'$.from_show')=? ORDER BY op.id",[$id,$id,$id]) as $operation) {
                    $sections = [...$sections,...self::sections('operation',(int)$operation['id'])];
                }
                foreach (DB::all('SELECT id FROM repairs WHERE show_id=? ORDER BY id',[$id]) as $repair) {
                    $sections = [...$sections,...self::sections('repair',(int)$repair['id'])];
                }
            }
            $sections[] = self::section('Show audit trail',DB::all('SELECT a.action AS Action,u.name AS User,a.created_at AS Timestamp,a.details AS Details FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity_type=? AND a.entity_id=? ORDER BY a.id',['show',$id]));
            return $sections;
        }
        if ($type==='operation') {
            $operation = DB::one('SELECT op.*,s.name AS show_name,s.theatre,u.name AS user_name FROM operations op JOIN shows s ON s.id=op.show_id JOIN users u ON u.id=op.created_by WHERE op.id=?',[$id])
                ?? throw new HttpException(404,'Operation not found.');
            $sections = [self::section($operation['type'] . ' Sheet #' . $id,[['Show'=>$operation['show_name'],'Theatre'=>$operation['theatre'],'Mode'=>$operation['mode'],'User'=>$operation['user_name'],'Timestamp'=>$operation['created_at']]])];
            if ($operation['revision_id']) {
                $sections = [...$sections,...self::sections('revision',(int)$operation['revision_id'])];
                $lines = Orders::lines((int)$operation['revision_id']);
                $partial = array_sum(array_column($lines,'pulled'))<array_sum(array_column($lines,'quantity'));
                $sections[] = self::section('Pull completion',[['Status'=>$partial?'Partial Pull':'Complete Pull','Requested'=>array_sum(array_column($lines,'quantity')),'Pulled'=>array_sum(array_column($lines,'pulled'))]]);
            }
            $sections[] = self::section('Serialized asset events',DB::all('SELECT a.asset_tag AS Asset,i.name AS Equipment,e.state AS Action,e.details AS Details,e.created_at AS Timestamp FROM asset_events e JOIN assets a ON a.id=e.asset_id JOIN item_types i ON i.id=a.item_type_id WHERE e.operation_id=? ORDER BY e.id',[$id]));
            return $sections;
        }
        if ($type==='repair') {
            $repair = DB::one('SELECT a.asset_tag AS Asset,i.name AS Equipment,r.description AS Description,r.status AS Status,s.name AS Show_Name,u.name AS Created_By,r.created_at AS Opened,r.completed_at AS Completed FROM repairs r JOIN assets a ON a.id=r.asset_id JOIN item_types i ON i.id=a.item_type_id LEFT JOIN shows s ON s.id=r.show_id JOIN users u ON u.id=r.created_by WHERE r.id=?',[$id])
                ?? throw new HttpException(404,'Repair not found.');
            return [self::section('Repair Ticket #' . $id,[$repair]),self::section('Repair history',DB::all("SELECT action AS Action,details AS Details,created_at AS Timestamp FROM audit_logs WHERE entity_type='repair' AND entity_id=? ORDER BY id",[$id]))];
        }
        if ($type==='timeline') {
            $where = ['1=1'];
            $params = [];
            foreach (['show'=>'s.id','theatre'=>'s.theatre','category'=>'c.id','subcategory'=>'f.id','item_type'=>'i.id','status'=>'a.state'] as $key=>$column) {
                if (($filters[$key] ?? '')!=='') {
                    $where[] = "$column=?";
                    $params[] = $filters[$key];
                }
            }
            if (($filters['q'] ?? '')!=='') {
                $where[] = 'a.asset_tag LIKE ?';
                $params[] = '%' . $filters['q'] . '%';
            }
            $assetWhere = $where;
            $assetParams = $params;
            if (($filters['start'] ?? '')!=='' && ($filters['end'] ?? '')!=='') {
                $where[] = 'x.start_date<=? AND (x.ended_at IS NULL OR DATE(x.ended_at)>=?)';
                $params[] = $filters['end'];
                $params[] = $filters['start'];
            }
            $assignments = DB::all("SELECT a.asset_tag AS Asset,i.name AS Equipment,s.name AS Show_Name,op.type AS Operation,
                x.start_date AS Prep,x.return_date AS Due,x.ended_at AS Ended,x.end_reason AS End_Reason FROM assignments x JOIN assets a ON a.id=x.asset_id
                JOIN item_types i ON i.id=a.item_type_id JOIN folders f ON f.id=i.folder_id JOIN folders c ON c.id=f.parent_id
                JOIN shows s ON s.id=x.show_id JOIN operations op ON op.id=x.operation_id WHERE " . implode(' AND ',$where) . ' ORDER BY x.start_date,a.asset_tag',$params);
            $repairWhere = $assetWhere;
            $repairParams = $assetParams;
            if (($filters['start'] ?? '')!=='' && ($filters['end'] ?? '')!=='') {
                $repairWhere[] = 'DATE(r.created_at)<=? AND (r.completed_at IS NULL OR DATE(r.completed_at)>=?)';
                $repairParams[] = $filters['end'];
                $repairParams[] = $filters['start'];
            }
            $repairs = DB::all("SELECT a.asset_tag AS Asset,i.name AS Equipment,r.status AS Status,r.created_at AS Opened,r.completed_at AS Completed
                FROM repairs r JOIN assets a ON a.id=r.asset_id JOIN item_types i ON i.id=a.item_type_id JOIN folders f ON f.id=i.folder_id
                JOIN folders c ON c.id=f.parent_id LEFT JOIN shows s ON s.id=r.show_id WHERE " . implode(' AND ',$repairWhere) . ' ORDER BY r.created_at',$repairParams);
            $commitments = [];
            foreach (DB::all('SELECT i.id,i.name,i.folder_id,f.parent_id FROM item_types i JOIN folders f ON f.id=i.folder_id') as $item) {
                if ((!empty($filters['item_type']) && (int)$filters['item_type']!==(int)$item['id']) ||
                    (!empty($filters['subcategory']) && (int)$filters['subcategory']!==(int)$item['folder_id']) ||
                    (!empty($filters['category']) && (int)$filters['category']!==(int)$item['parent_id'])) {
                    continue;
                }
                foreach (Planning::commitments((int)$item['id']) as $commitment) {
                    $show = Shows::find((int)$commitment['show_id']);
                    if (!$commitment['quantity'] || (!empty($filters['show']) && (int)$filters['show']!==(int)$show['id']) ||
                        (!empty($filters['theatre']) && $filters['theatre']!==$show['theatre']) ||
                        (!empty($filters['start']) && $commitment['return_date']<$filters['start']) ||
                        (!empty($filters['end']) && $commitment['start_date']>$filters['end'])) {
                        continue;
                    }
                    $commitments[] = ['Equipment'=>$item['name'],'Show_Name'=>$show['name'],'Quantity'=>$commitment['quantity'],
                        'Prep'=>$commitment['start_date'],'Due'=>$commitment['return_date'],'Line'=>$commitment['line_id']];
                }
            }
            return [self::section('Timeline report criteria',[$filters ?: ['Scope'=>'All shop history']]),
                self::section('Serialized assignments, transfers and returns',$assignments),
                self::section('Repair windows',$repairs),
                self::section('Item-type future commitments (not yet serialized)',$commitments)];
        }
        $queries = [
            'inventory'=>["SELECT a.asset_tag AS Asset,i.name AS Equipment,a.serial_number AS Serial,a.state AS Status FROM assets a JOIN item_types i ON i.id=a.item_type_id ORDER BY i.name,a.asset_tag",[]],
            'missing'=>["SELECT a.asset_tag AS Asset,i.name AS Equipment,a.serial_number AS Serial,s.name AS Show_Name,e.created_at AS Marked_Missing,u.name AS Recorded_By
                FROM assets a JOIN item_types i ON i.id=a.item_type_id LEFT JOIN asset_events e ON e.id=(
                    SELECT MAX(z.id) FROM asset_events z WHERE z.asset_id=a.id AND z.state='Return' AND JSON_EXTRACT(z.details,'$.condition')='Missing')
                LEFT JOIN shows s ON s.id=e.show_id LEFT JOIN users u ON u.id=e.created_by WHERE a.state='Missing' ORDER BY a.asset_tag",[]],
            'utilization'=>["SELECT a.asset_tag AS Asset,i.name AS Equipment,COUNT(x.id) AS Assignments,COALESCE(SUM(DATEDIFF(COALESCE(DATE(x.ended_at),CURRENT_DATE()),DATE(x.assigned_at))+1),0) AS Days_Used FROM assets a JOIN item_types i ON i.id=a.item_type_id LEFT JOIN assignments x ON x.asset_id=a.id GROUP BY a.id,a.asset_tag,i.name ORDER BY Days_Used DESC",[]],
            'timeline'=>["SELECT a.asset_tag AS Asset,s.name AS Show_Name,x.start_date AS Prep,x.return_date AS Due,x.assigned_at AS Assigned,x.ended_at AS Ended,x.end_reason AS End_Reason FROM assignments x JOIN assets a ON a.id=x.asset_id JOIN shows s ON s.id=x.show_id ORDER BY x.start_date,a.asset_tag",[]],
            'repairs'=>["SELECT a.asset_tag AS Asset,r.description AS Description,r.status AS Status,r.created_at AS Opened FROM repairs r JOIN assets a ON a.id=r.asset_id ORDER BY r.id DESC",[]],
            'packages'=>["SELECT p.name AS Package,i.name AS Component,c.quantity AS Ratio FROM package_components c JOIN packages p ON p.id=c.package_id JOIN item_types i ON i.id=c.item_type_id ORDER BY p.name",[]],
            'rules'=>["SELECT r.name AS Rule,s.name AS Equipment,r.source_quantity AS Source_Ratio,d.name AS Accessory,r.dependency_quantity AS Accessory_Ratio,r.mode AS Enforcement FROM equipment_rules r JOIN item_types s ON s.id=r.source_type_id JOIN item_types d ON d.id=r.dependency_type_id ORDER BY r.id",[]],
        ];
        if (!isset($queries[$type])) {
            throw new HttpException(404,'Report type not found.');
        }
        return [self::section(ucfirst($type) . ' Report',DB::all(...$queries[$type]))];
    }

    public static function generate(string $type, int $id, array $filters = []): int
    {
        $sections = self::sections($type,$id,$filters);
        $title = match ($type) {'revision'=>'Shop Order / Revision Receipt','operation'=>'Shop Operation Receipt','show'=>'Show Packet','archive'=>'Show Archive Book',default=>ucfirst($type) . ' Report'};
        $generated = gmdate('Y-m-d H:i:s') . ' UTC';
        $user = Auth::user();
        $brand = Settings::get('brand_name',Config::get('name'));
        $logo = Settings::get('logo_light') ?: Settings::get('logo_dark');
        $logoPath = $logo!=='' ? ROOT . '/storage/uploads/' . basename($logo) : null;
        $attachments = $type==='archive'?DB::all('SELECT * FROM files WHERE show_id=? AND mime<>? ORDER BY id',[$id,'application/pdf']):[];
        ob_start();
        require ROOT . '/app/Views/pdf/document.php';
        $html = ob_get_clean();
        $options = new Options(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false,'chroot'=>ROOT . '/storage','defaultFont'=>'DejaVu Sans']);
        $pdf = new Dompdf($options);
        $pdf->setPaper('letter');
        $pdf->loadHtml($html);
        $pdf->render();
        $pdf->getCanvas()->page_text(36,755,'Page {PAGE_NUM} of {PAGE_COUNT}',null,8,[0.4,0.4,0.4]);
        $name = bin2hex(random_bytes(24)) . '.pdf';
        $path = ROOT . '/storage/pdfs/' . $name;
        $bytes = $pdf->output();
        if ($type==='archive') {
            $archive = new ArchivePdf();
            $archive->AliasNbPages();
            $archive->attribution = iconv('UTF-8','windows-1252//TRANSLIT',$brand . ' | ' . $generated . ' | ' . $user['name']);
            $append = function (mixed $source, string $label) use ($archive): void {
                $pages = $archive->setSourceFile($source);
                for ($page=1;$page<=$pages;$page++) {
                    $template = $archive->importPage($page);
                    $size = $archive->getTemplateSize($template);
                    $archive->AddPage($size['orientation'],[$size['width'],$size['height']+24]);
                    $archive->SetFont('Helvetica','',8);
                    $archive->SetTextColor(40,50,70);
                    $archive->SetXY(5,3);
                    $archive->Cell(0,5,iconv('UTF-8','windows-1252//TRANSLIT',$label));
                    $archive->useTemplate($template,0,12,$size['width'],$size['height']);
                }
            };
            $append(\setasign\Fpdi\PdfParser\StreamReader::createByString($bytes),'Show archive summary');
            $historical = DB::all("SELECT d.* FROM documents d WHERE
                (d.type='revision' AND EXISTS(SELECT 1 FROM revisions r JOIN shop_orders o ON o.id=r.order_id WHERE r.id=d.entity_id AND o.show_id=?)) OR
                (d.type='operation' AND EXISTS(SELECT 1 FROM operations op LEFT JOIN asset_events e ON e.operation_id=op.id WHERE op.id=d.entity_id AND (op.show_id=? OR e.show_id=? OR JSON_EXTRACT(e.details,'$.from_show')=?))) OR
                (d.type='repair' AND EXISTS(SELECT 1 FROM repairs r WHERE r.id=d.entity_id AND r.show_id=?))
                ORDER BY d.id",[$id,$id,$id,$id,$id]);
            foreach ($historical as $document) {
                $source = ROOT . '/storage/pdfs/' . basename($document['stored_name']);
                if (!is_file($source) || !hash_equals($document['sha256'],hash_file('sha256',$source))) {
                    throw new \RuntimeException('A historical receipt is missing or failed integrity verification. Archive generation stopped.');
                }
                $append($source,'Historical ' . $document['type'] . ' receipt #' . $document['id'] . ' | ' . $document['created_at']);
            }
            foreach (DB::all("SELECT * FROM files WHERE show_id=? AND mime='application/pdf' ORDER BY id",[$id]) as $file) {
                $append(ROOT . '/storage/uploads/' . basename($file['stored_name']),'Show attachment: ' . $file['original_name']);
            }
            $bytes = $archive->Output('S');
        }
        if (file_put_contents($path,$bytes,LOCK_EX)===false) {
            throw new \RuntimeException('Unable to write PDF. Check storage permissions.');
        }
        self::$pending[] = $path;
        $documentId = DB::insert('INSERT INTO documents(type,entity_id,stored_name,sha256,created_by) VALUES (?,?,?,?,?)',
            [$type,$id,$name,hash('sha256',$bytes),$user['id']]);
        Audit::record('document.generated','document',$documentId,['type'=>$type,'entity_id'=>$id]);
        if (!DB::connection()->inTransaction()) {
            self::commitPending();
        }
        return $documentId;
    }
}
