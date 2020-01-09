<?php
// Icinga PDF Export | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\Pdfexport\ProvidedHook;

use Exception;
use Icinga\Application\Config;
use Icinga\Application\Hook;
use Icinga\Application\Hook\PdfexportHook;
use Icinga\Application\Icinga;
use Icinga\Module\Pdfexport\HeadlessChrome;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use iio\libmergepdf\Driver\TcpdiDriver;
use iio\libmergepdf\Merger;

class Pdfexport extends PdfexportHook
{
    public static function first()
    {
        $pdfexport = null;

        if (Hook::has('Pdfexport')) {
            $pdfexport = Hook::first('Pdfexport');

            if (! $pdfexport->isSupported()) {
                throw new Exception(
                    sprintf("Can't export: %s does not support exporting PDFs", get_class($pdfexport))
                );
            }
        }

        if (! $pdfexport) {
            throw new Exception("Can't export: No module found which provides PDF export");
        }

        return $pdfexport;
    }

    public static function getBinary()
    {
        return Config::module('pdfexport')->get('chrome', 'binary', '/bin/google-chrome');
    }

    public static function getHost()
    {
        return Config::module('pdfexport')->get('chrome', 'host');
    }

    public static function getPort()
    {
        return Config::module('pdfexport')->get('chrome', 'port', 9222);
    }

    public function isSupported()
    {
        try {
            return $this->Chrome()->getVersion() >= 59;
        } catch (Exception $e) {
            return false;
        }
    }

    public function htmlToPdf($html)
    {
        // Keep reference to the chrome object because it is using temp files which are automatically removed when
        // the object is destructed
        $chrome = $this->Chrome();

        return $chrome->fromHtml($html)->toPdf();
    }

    public function streamPdfFromHtml($html, $filename)
    {
        $filename = basename($filename, '.pdf') . '.pdf';

        // Keep reference to the chrome object because it is using temp files which are automatically removed when
        // the object is destructed
        $chrome = $this->Chrome();

        $pdf = $chrome->fromHtml($html)->toPdf();

        if ($html instanceof PrintableHtmlDocument && ($coverPage = $html->getCoverPage()) !== null) {
            $coverPagePdf = $chrome
                ->fromHtml((new PrintableHtmlDocument())
                    ->add($coverPage)
                    ->addAttributes($html->getAttributes())
                    ->removeMargins()
                )
                ->toPdf();

            $merger = new Merger(new TcpdiDriver());
            $merger->addRaw($coverPagePdf);
            $merger->addRaw($pdf);

            $pdf = $merger->merge();
        }

        Icinga::app()->getResponse()
            ->setHeader('Content-Type', 'application/pdf', true)
            ->setHeader('Content-Disposition', "inline; filename=\"$filename\"", true)
            ->setBody($pdf)
            ->sendResponse();

        exit;
    }

    /**
     * Create an instance of HeadlessChrome from configuration
     *
     * @return HeadlessChrome
     */
    protected function Chrome()
    {
        $chrome = new HeadlessChrome();
        $chrome->setBinary(static::getBinary());

        if (($host = static::getHost()) !== null) {
            $chrome->setRemote($host, static::getPort());
        }

        return $chrome;
    }
}
