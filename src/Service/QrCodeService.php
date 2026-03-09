<?php

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    public function generate(string $data): string
    {
        $qrCode = new QrCode($data);
        $writer = new SvgWriter();
        $result = $writer->write($qrCode);

        // Return a data URI (base64) so templates can use the string directly as image src
        return 'data:image/svg+xml;base64,' . base64_encode($result->getString());
    }
}

