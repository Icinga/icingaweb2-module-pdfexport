<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\ProvidedHook;

use Exception;
use Icinga\Application\Hook;
use Icinga\Application\Hook\PdfexportHook;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;
use Icinga\Application\Web;
use Icinga\File\Storage\TemporaryLocalFileStorage;
use Icinga\Module\Pdfexport\BackendLocator;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use ipl\Html\HtmlString;
use Karriere\PdfMerge\PdfMerge;

class Pdfexport extends PdfexportHook
{
    public static function first()
    {
        $pdfexport = null;

        if (Hook::has('Pdfexport')) {
            $pdfexport = Hook::first('Pdfexport');

            if (! $pdfexport->isSupported()) {
                throw new Exception(
                    sprintf("Can't export: %s does not support exporting PDFs", get_class($pdfexport)),
                );
            }
        }

        if (! $pdfexport) {
            throw new Exception("Can't export: No module found which provides PDF export");
        }

        return $pdfexport;
    }

    public function isSupported(): bool
    {
        $locator = new BackendLocator();
        try {
            $backend = $locator->getFirstSupportedBackend();
            return $backend !== null;
        } catch (Exception $e) {
            Logger::warning("No supported PDF backend available.");
            return false;
        }
    }

    public function streamPdfFromHtml($html, $filename): void
    {
        $filename = basename($filename, '.pdf') . '.pdf';

        $document = $this->getPrintableHtmlDocument($html);

        $locator = new BackendLocator();
        $backend = $locator->getFirstSupportedBackend();
        if ($backend === null) {
            Logger::warning("No supported PDF backend available.");
        }

        $pdf = $backend->toPdf($document);

        if ($html instanceof PrintableHtmlDocument) {
            $coverPage = $html->getCoverPage();
            if ($coverPage !== null) {
                $coverPageDocument = $this->getPrintableHtmlDocument($coverPage);
                $coverPageDocument->addAttributes($html->getAttributes());
                $coverPageDocument->removeMargins();

                $coverPagePdf = $backend->toPdf($coverPageDocument);

                $pdf = $this->mergePdfs($coverPagePdf, $pdf);
            }
        }

        $this->emit($pdf, $filename);

        exit;
    }

    protected function emit(string $pdf, string $filename): void
    {
        /** @var Web $app */
        $app = Icinga::app();
        $app->getResponse()
            ->setHeader('Content-Type', 'application/pdf', true)
            ->setHeader('Content-Disposition', "inline; filename=\"$filename\"", true)
            ->setBody($pdf)
            ->sendResponse();
    }

    protected function getPrintableHtmlDocument($html): PrintableHtmlDocument
    {
        if ($html instanceof PrintableHtmlDocument) {
            return $html;
        }
        return (new PrintableHtmlDocument())
            ->setContent(HtmlString::create($html));
    }

    protected function mergePdfs(string ...$pdfs): string
    {
        $merger = new PdfMerge();
        $storage = new TemporaryLocalFileStorage();

        try {
            foreach ($pdfs as $i => $pdf) {
                $storage->create($i, $pdf);
                $merger->add($storage->resolvePath($i));
            }

            return $merger->merge('', 'S');
        } finally {
            foreach ($pdfs as $i => $_) {
                $storage->delete($i);
            }
        }
    }
}
