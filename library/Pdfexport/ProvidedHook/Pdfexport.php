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
use Icinga\Exception\IcingaException;
use Icinga\File\Storage\TemporaryLocalFileStorage;
use Icinga\Module\Pdfexport\BackendLocator;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use ipl\Html\HtmlString;
use ipl\Html\ValidHtml;
use Karriere\PdfMerge\PdfMerge;
use RuntimeException;
use Throwable;

/**
 * PDF Export Hook implementation that forwards the PDF generation to the first
 * supported PDF backend
 */
class Pdfexport extends PdfexportHook
{
    protected ?BackendLocator $locator = null;

    /**
     * Get the first supported hook
     *
     * This compatibility implementation matches the one in the base class.
     * Reporting 1.1 and Icinga Web 2.13 require it.
     *
     * @return PdfexportHook The first supported registered exporter
     *
     * @throws RuntimeException If no supported PDF exporter is available
     *
     * @deprecated Use {@see PdfexportHook::first()} instead
     */
    public static function first(): PdfexportHook
    {
        // The registry validates each instance against the hook's base class.
        /** @var PdfexportHook[] $exporters */
        $exporters = Hook::all('Pdfexport');
        foreach ($exporters as $exporter) {
            try {
                if ($exporter->isSupported()) {
                    return $exporter;
                }
            } catch (Throwable $e) {
                Logger::error(
                    "PDF exporter reported an error during support check: %s\n%s",
                    $e,
                    IcingaException::getConfidentialTraceAsString($e),
                );
            }
        }

        throw new RuntimeException('No supported PDF exporter available');
    }

    /**
     * Get the backend locator instance, creating it if necessary
     *
     * @return BackendLocator
     */
    protected function getLocator(): BackendLocator
    {
        return $this->locator ??= new BackendLocator();
    }

    public function isSupported(): bool
    {
        $locator = $this->getLocator();
        try {
            return $locator->getFirstSupportedBackend() !== null;
        } catch (Exception $e) {
            Logger::warning(
                "PDF backend support check failed: %s\n%s",
                $e->getMessage(),
                IcingaException::getConfidentialTraceAsString($e),
            );

            return false;
        }
    }

    /**
     * Render HTML to PDF and stream it to the client
     *
     * @param ValidHtml|string $html Trusted rendered HTML to export
     * @param string $filename The filename for the generated PDF
     *
     * @return never
     *
     * @throws RuntimeException If no supported PDF backend is available
     */
    public function streamPdfFromHtml($html, $filename): never
    {
        $this->emit($this->htmlToPdf($html), basename($filename, '.pdf') . '.pdf');
    }

    /**
     * Render HTML to PDF
     *
     * @param ValidHtml|string $html Trusted rendered HTML to export
     *
     * @return string The generated PDF contents
     *
     * @throws RuntimeException If no supported PDF backend is available
     */
    public function htmlToPdf($html): string
    {
        $document = $this->getPrintableHtmlDocument($html);

        $backend = $this->getLocator()->getFirstSupportedBackend();
        if ($backend === null) {
            throw new RuntimeException('No supported PDF backend available.');
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

    /**
     * Emit a PDF file as the response with the appropriate headers
     *
     * @param string $pdf The content of the PDF file to be emitted.
     * @param string $filename The filename to be used for the emitted PDF.
     *
     * @return never
     */
    protected function emit(string $pdf, string $filename): never
    {
        /** @var Web $app */
        $app = Icinga::app();
        $app->getResponse()
            ->setHeader('Content-Type', 'application/pdf', true)
            ->setHeader('Content-Disposition', "inline; filename=\"$filename\"", true)
            ->setBody($pdf)
            ->sendResponse();

        exit;
    }

    /**
     * Get a printable document from trusted rendered HTML
     *
     * Printable documents retain their print settings. Strings contain trusted markup
     * from legacy export callers and are included without escaping.
     *
     * @param ValidHtml|string $html The HTML content to convert
     *
     * @return PrintableHtmlDocument
     */
    protected function getPrintableHtmlDocument(ValidHtml|string $html): PrintableHtmlDocument
    {
        if ($html instanceof PrintableHtmlDocument) {
            return $html;
        }

        return (new PrintableHtmlDocument())
            ->setContent(is_string($html) ? HtmlString::create($html) : $html);
    }

    /**
     * Merge PDF contents into a single document
     *
     * @param string ...$pdfs The PDF contents to merge
     *
     * @return string The merged PDF contents
     *
     * @throws \Karriere\PdfMerge\Exceptions\NoFilesDefinedException If no PDF contents are provided
     */
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
