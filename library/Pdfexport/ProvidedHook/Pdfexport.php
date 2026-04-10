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
use ipl\Html\ValidHtml;
use Karriere\PdfMerge\PdfMerge;
use RuntimeException;

class Pdfexport extends PdfexportHook
{
    protected ?BackendLocator $locator = null;

    /**
     * Get the first hook.
     * Note: This function is the exact same as the one if the base class.
     * It can be removed after we decide to remove compatibility with current
     * reporting (1.1) and icingaweb2 (2.13) versions.
     *
     * @return static
     */
    public static function first()
    {
        if (! Hook::has('Pdfexport')) {
            throw new RuntimeException('No PDF exporter available');
        }
        $pdfexport = Hook::first('Pdfexport');
        if (! $pdfexport->isSupported()) {
            throw new RuntimeException('PDF exporter is not supported');
        }
        return $pdfexport;
    }

    /**
     * Get the backend locator instance, creating it if necessary
     * @return BackendLocator
     */
    protected function getLocator(): BackendLocator
    {
        if (! $this->locator) {
            $this->locator = new BackendLocator();
        }
        return $this->locator;
    }

    public function isSupported()
    {
        $locator = $this->getLocator();
        try {
            $backend = $locator->getFirstSupportedBackend();
            return $backend !== null;
        } catch (Exception $e) {
            Logger::warning("No supported PDF backend available.");
            return false;
        }
    }

    public function streamPdfFromHtml($html, $filename)
    {
        $pdf = $this->htmlToPdf($html);
        $filename = basename($filename, '.pdf') . '.pdf';

        $this->emit($pdf, $filename);

        exit;
    }

    public function htmlToPdf($html)
    {
        $document = $this->getPrintableHtmlDocument($html);

        $locator = $this->getLocator();
        $backend = $locator->getFirstSupportedBackend();
        if ($backend === null) {
            Logger::warning("No supported PDF backend available.");
        }

        $pdf = $backend->toPdf($document);

        if ($html instanceof PrintableHtmlDocument && $backend->supportsCoverPage()) {
            $coverPage = $html->getCoverPage();
            if ($coverPage !== null) {
                $coverPageDocument = $this->getPrintableHtmlDocument($coverPage);
                $coverPageDocument->addAttributes($html->getAttributes());
                $coverPageDocument->removeMargins();

                $coverPagePdf = $backend->toPdf($coverPageDocument);

                $backend->close();

                $pdf = $this->mergePdfs($coverPagePdf, $pdf);
            }
        }

        $backend->close();
        unset($coverPage);

        return $pdf;
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

    protected function getPrintableHtmlDocument(ValidHtml $html): PrintableHtmlDocument
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
