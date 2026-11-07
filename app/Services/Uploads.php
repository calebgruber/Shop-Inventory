<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;

final class Uploads
{
    public static function receive(array $file, bool $branding = false): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new HttpException(422,'Upload failed. Select a valid file and try again.');
        }
        $size = filesize($file['tmp_name']);
        if ($size===false || $size<1 || $size>10485760) {
            throw new HttpException(422,'Uploads must be between 1 byte and 10 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
        if (!$branding) {
            $allowed += ['application/pdf'=>'pdf','text/plain'=>'txt','text/csv'=>'csv'];
        }
        if (!isset($allowed[$mime])) {
            throw new HttpException(422,'Allowed files: PNG, JPEG, WebP' . ($branding?'':', PDF, TXT or CSV') . '.');
        }
        if (str_starts_with($mime,'image/')) {
            $dimensions = getimagesize($file['tmp_name']);
            if (!$dimensions || $dimensions[0]>6000 || $dimensions[1]>6000) {
                throw new HttpException(422,'Images must be valid and no larger than 6000 x 6000 pixels.');
            }
        }
        $name = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'],ROOT . '/storage/uploads/' . $name)) {
            throw new \RuntimeException('Unable to save upload. Check storage permissions.');
        }
        return ['name'=>$name,'mime'=>$mime,'size'=>$size];
    }

    public static function showFile(int $showId, array $file): int
    {
        Shows::editable(Shows::find($showId));
        $upload = self::receive($file);
        try {
            if ($upload['mime']==='application/pdf') {
                $parser = new \setasign\Fpdi\Fpdi();
                try {
                    $pages = $parser->setSourceFile(ROOT . '/storage/uploads/' . $upload['name']);
                    for ($page=1;$page<=$pages;$page++) {
                        $parser->importPage($page);
                    }
                } catch (\setasign\Fpdi\FpdiException $error) {
                    throw new HttpException(422,'This PDF cannot be included in archive books. Upload an unencrypted, flattened PDF 1.4-compatible export.');
                }
            }
            return DB::transaction(function () use ($showId,$file,$upload): int {
                Shows::editable(Shows::find($showId,true));
                $original = Validation::text(basename($file['name']),'File name',200);
                $id = DB::insert('INSERT INTO files(show_id,original_name,stored_name,mime,size_bytes,created_by) VALUES (?,?,?,?,?,?)',
                    [$showId,$original,$upload['name'],$upload['mime'],$upload['size'],Auth::user()['id']]);
                Audit::record('file.uploaded','show',$showId,['file_id'=>$id,'name'=>$original]);
                Documents::generate('show',$showId);
                return $id;
            });
        } catch (\Throwable $error) {
            unlink(ROOT . '/storage/uploads/' . $upload['name']);
            throw $error;
        }
    }
}
