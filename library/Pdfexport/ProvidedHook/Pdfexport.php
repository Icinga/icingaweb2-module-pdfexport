<?php
// Icinga PDF Export | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\Pdfexport\ProvidedHook;

use Icinga\Application\Config;
use Icinga\Application\Hook\PdfexportHook;
use Icinga\Application\Icinga;
use Icinga\Module\Pdfexport\HeadlessChrome;

class Pdfexport extends PdfexportHook
{
    public static function getBinary()
    {
        return Config::module('pdfexport')->get('chrome', 'binary', '/bin/google-chrome');
    }

    public function isSupported()
    {
        try {
            return (new HeadlessChrome())->setBinary(static::getBinary())->getVersion() >= 59;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function htmlToPdf($html)
    {
        // Keep reference to the chrome object because it is using temp files which are automatically removed when
        // the object is destructed
        $chrome = new HeadlessChrome();

        $pdf = $chrome
            ->setBinary(static::getBinary())
            ->fromHtml($html)
            ->toPdf();

        return $pdf;
    }

    public function streamPdfFromHtml($html, $filename)
    {
        $filename = basename($filename, '.pdf') . '.pdf';

        // Keep reference to the chrome object because it is using temp files which are automatically removed when
        // the object is destructed
        $chrome = new HeadlessChrome();

        $pdf = $chrome
            ->setBinary(static::getBinary())
            ->fromHtml($html)
            ->toPdf();

        $response = Icinga::app()->getResponse();

        $response->setHeader('Content-Type', 'application/pdf', true);
        $response->setHeader('Content-Disposition', "inline; filename=\"$filename\"", true);
        $response->sendHeaders();

        readfile($pdf);

        exit;
    }
}
