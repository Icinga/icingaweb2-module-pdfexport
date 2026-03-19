<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\WebDriver\Capabilities;
use Icinga\Module\Pdfexport\WebDriver\ElementPresentCondition;
use Icinga\Module\Pdfexport\WebDriver\WebDriver;
use Icinga\Module\Pdfexport\WebDriver\Command;

class WebdriverBackend implements PfdPrintBackend
{
    protected WebDriver $driver;

    public function __construct(
        string $url,
        Capabilities $capabilities,
    ) {
        $this->driver = WebDriver::create($url, $capabilities);
    }

    public function __destruct()
    {
        $this->close();
    }

    protected function setContent(PrintableHtmlDocument $document): void
    {
        // This is horribly ugly, but it works for all browser backends
        $encoded = base64_encode($document);
        $this->driver->execute(
            Command::executeScript('document.head.remove();'),
        );
        $this->driver->execute(
            Command::executeScript("document.body.outerHTML = atob('$encoded');"),
        );
    }

    protected function waitForPageLoad(): void
    {
        $this->driver->wait(ElementPresentCondition::byTagName('body'));
    }

    protected function getPrintParameters(PrintableHtmlDocument $document): array
    {
        $parameters = [
            'background' => true,
        ];

        return array_merge(
            $parameters,
            $document->getPrintParametersForWebdriver(),
        );
    }

    protected function printToPdf(array $printParameters): string
    {
        $result = $this->driver->execute(
            Command::printPage($printParameters),
        );

        return base64_decode($result);
    }

    public function toPdf(PrintableHtmlDocument $document): string
    {
        $this->setContent($document);
        $this->waitForPageLoad();

        $printParameters = $this->getPrintParameters($document);

        return $this->printToPdf($printParameters);
    }

    public function isSupported(): bool
    {
        // TODO: Come up with a check
        return true;
    }

    public function close(): void
    {
        $this->driver->quit();
    }
}
