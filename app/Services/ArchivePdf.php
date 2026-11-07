<?php
declare(strict_types=1);
namespace App\Services;

use setasign\Fpdi\Fpdi;

final class ArchivePdf extends Fpdi
{
    public string $attribution = '';

    public function Footer(): void
    {
        $this->SetY(-9);
        $this->SetFont('Helvetica','',7);
        $this->SetTextColor(80,90,110);
        $this->Cell(0,4,$this->attribution . ' | Archive page ' . $this->PageNo() . '/{nb}',0,0,'C');
    }
}
