<?php
// Icinga PDF Export | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\Pdfexport\ProvidedHook;

use Icinga\Application\Config;
use Icinga\Application\Hook\PdfexportHook;
use Icinga\Application\Icinga;
use Icinga\Module\Pdfexport\HeadlessChrome;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use iio\libmergepdf\Driver\TcpdiDriver;
use iio\libmergepdf\Merger;

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

        $response = Icinga::app()->getResponse()
            ->setHeader('Content-Type', 'application/pdf', true)
            ->setHeader('Content-Disposition', "inline; filename=\"$filename\"", true);

        // Keep reference to the chrome object because it is using temp files which are automatically removed when
        // the object is destructed
        $chrome = new HeadlessChrome();

        $pdf = $chrome
            ->setBinary(static::getBinary())
            ->fromHtml($html)
            ->toPdf();

        if ($html instanceof PrintableHtmlDocument) {
            $coverPage = $html->getCoverPage();

            if ($coverPage !== null) {
                $coverPagePdf = $chrome
                    ->fromHtml((new PrintableHtmlDocument())->add($coverPage))
                    ->toPdf();
            }

            $merger = new Merger(new TcpdiDriver());
            $merger->addFile($coverPagePdf);
            $merger->addFile($pdf);

            $response->setBody($merger->merge());
            $response->sendResponse();
        } else {
            $response->sendHeaders();

            readfile($pdf);
        }

        exit;
    }
}
