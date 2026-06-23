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
     * Get the first hook.
     * Note: This function is the exact same as the one if the base class.
     * It can be removed after we decide to remove compatibility with current
     * reporting (1.1) and icingaweb2 (2.13) versions.
     *
     * @return static
     *
     * @deprecated Use {@see PdfexportHook::first()} instead
     */
    public static function first()
    {
        foreach (Hook::all('Pdfexport') as $exporter) {
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
            Logger::warning('No supported PDF backend available.');

            return null;
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
    }

    /**
     * Converts a ValidHtml object into a PrintableHtmlDocument instance
     *
     * If the provided ValidHtml is already an instance of PrintableHtmlDocument, it is returned as is.
     * Otherwise, a new PrintableHtmlDocument is created with the given HTML content.
     *
     * @param ValidHtml $html The HTML content to convert
     *
     * @return PrintableHtmlDocument
     */
    protected function getPrintableHtmlDocument(ValidHtml $html): PrintableHtmlDocument
    {
        if ($html instanceof PrintableHtmlDocument) {
            return $html;
        }
        return (new PrintableHtmlDocument())
            ->setContent(HtmlString::create($html));
    }

    /**
     * Merge multiple PDF files into a single one
     *
     * @param string ...$pdfs The paths to the PDF files to merge
     *
     * @return string the resulting PDF content
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
