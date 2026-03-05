<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\ProvidedHook;

use Exception;
use Icinga\Application\Config;
use Icinga\Application\Hook;
use Icinga\Application\Hook\PdfexportHook;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;
use Icinga\File\Storage\TemporaryLocalFileStorage;
use Icinga\Module\Pdfexport\Backend\Chromedriver;
use Icinga\Module\Pdfexport\Backend\Geckodriver;
use Icinga\Module\Pdfexport\Backend\HeadlessChromeBackend;
use Icinga\Module\Pdfexport\Backend\PfdPrintBackend;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\WebDriverType;
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
        try {
            $driver = $this->getBackend();
            return $driver->isSupported();
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getBinary(): string
    {
        return Config::module('pdfexport')->get('chrome', 'binary', '/usr/bin/google-chrome');
    }

    public static function getForceTempStorage(): bool
    {
        return (bool)Config::module('pdfexport')->get('chrome', 'force_temp_storage', '0');
    }

    public static function getHost(): ?string
    {
        return Config::module('pdfexport')->get('chrome', 'host');
    }

    public static function getPort(): int
    {
        return Config::module('pdfexport')->get('chrome', 'port', 9222);
    }

    public static function getWebDriverHost(): ?string
    {
        return Config::module('pdfexport')->get('webdriver', 'host');
    }

    public static function getWebDriverPort(): int
    {
        return (int)Config::module('pdfexport')->get('webdriver', 'port', 4444);
    }

    public static function getWebDriverType(): WebDriverType
    {
        $str = Config::module('pdfexport')->get('webdriver', 'type', 'chrome');
        return WebDriverType::from($str);
    }

    public function streamPdfFromHtml($html, $filename): void
    {
        $filename = basename($filename, '.pdf') . '.pdf';

        $document = $this->getPrintableHtmlDocument($html);

        $driver = $this->getBackend();

        $pdf = $driver->toPdf($document);

        if ($html instanceof PrintableHtmlDocument) {
            $coverPage = $html->getCoverPage();
            if ($coverPage !== null) {
                $coverPageDocument = $this->getPrintableHtmlDocument($coverPage);
                $coverPageDocument->addAttributes($html->getAttributes());
                $coverPageDocument->removeMargins();

                $coverPagePdf = $driver->toPdf($coverPageDocument);

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

    protected function getBackend(): PfdPrintBackend
    {
        try {
            if (($host = $this->getWebDriverHost()) !== null) {
                $port = $this->getWebDriverPort();
                $url = "$host:$port";
                $type = $this->getWebDriverType();
                return match ($type) {
                    WebDriverType::Chrome => new Chromedriver($url),
                    WebDriverType::Firefox => new Geckodriver($url),
                    default => throw new Exception("Invalid webdriver type $type->value"),
                };
            }
        } catch (Exception $e) {
            Logger::error("Error while creating WebDriver backend: " . $e->getMessage());
        }

        try {
            if (($host = $this->getHost()) !== null) {
                return HeadlessChromeBackend::createRemote(
                    $host,
                    $this->getPort(),
                );
            }
        } catch (Exception $e) {
            Logger::error("Error while creating remote HeadlessChrome backend: " . $e->getMessage());
        }

        try {
            if (($binary = $this->getBinary()) !== null) {
                return HeadlessChromeBackend::createLocal(
                    $binary,
                    $this->getForceTempStorage(),
                );
            }
        } catch (Exception $e) {
            Logger::error("Error while creating local HeadlessChrome backend: " . $e->getMessage());
        }

        throw new Exception("No PDF print backend available.");
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
