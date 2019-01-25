<?php

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
        return (new HeadlessChrome())->setBinary(static::getBinary())->getVersion() >= 59;
    }

    public function htmlToPdf($html)
    {
        $style = strpos($html, '</style>');
        if ($style !== false) {
            // Inject custom CSS
            $css = <<<'CSS'
@page {
  margin: 1.6cm;
}

body {
  margin: 0;
}

body > img {
  margin-top: -1em;
}
</style>
CSS;
            $html = substr($html, 0, $style) . $css . substr($html, $style + 8);
        }

        $footer = strrpos($html, '<div id="page-footer">');
        if ($footer !== false) {
            // Hide page footer
            $html = substr($html, 0, $footer)
                . '<div id="page-footer" style="display: none !important;">'
                . substr($html, $footer + 22);
        }

        // Keep reference to the chrome object because it is using temp files which are automatically removed when
        // the object is destructed
        $chrome = new HeadlessChrome();

        $pdf = $chrome
            ->setBinary(static::getBinary())
            ->fromHtml($html)
            ->toPdf('direct');

        return file_get_contents($pdf);
    }

    public function streamPdfFromHtml($html, $filename)
    {
        $filename = basename($filename, '.pdf') . '.pdf';

        $style = strpos($html, '</style>');
        if ($style !== false) {
            // Inject custom CSS
            $css = <<<'CSS'
@page {
  margin: 1.6cm;
}

body {
  margin: 0;
}

body > img {
  margin-top: -1em;
}
</style>
CSS;
            $html = substr($html, 0, $style) . $css . substr($html, $style + 8);
        }

        $footer = strrpos($html, '<div id="page-footer">');
        if ($footer !== false) {
            // Hide page footer
            $html = substr($html, 0, $footer)
                . '<div id="page-footer" style="display: none !important;">'
                . substr($html, $footer + 22);
        }

        // Keep reference to the chrome object because it is using temp files which are automatically removed when
        // the object is destructed
        $chrome = new HeadlessChrome();

        $pdf = $chrome
            ->setBinary(static::getBinary())
            ->fromHtml($html)
            ->toPdf($filename);

        $response = Icinga::app()->getResponse();

        $response->setHeader('Content-Type', 'application/pdf', true);
        $response->setHeader('Content-Disposition', "inline; filename=\"$filename\"", true);
        $response->sendHeaders();

        readfile($pdf);

        exit;
    }
}
