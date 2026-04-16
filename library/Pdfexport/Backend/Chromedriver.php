<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Exception;
use Icinga\Application\Icinga;
use Icinga\Module\Pdfexport\ChromeDevTools\ChromeDevTools;
use Icinga\Module\Pdfexport\ChromeDevTools\Command as DevToolsCommand;
use Icinga\Module\Pdfexport\WebDriver\Command;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\WebDriver\Capabilities;
use Icinga\Module\Pdfexport\WebDriver\ElementPresentCondition;

class Chromedriver extends WebdriverBackend
{
    protected ?ChromeDevTools $dcp = null;

    public function __construct(string $url)
    {
        parent::__construct($url, Capabilities::chrome());
    }

    protected function setContent(PrintableHtmlDocument $document): void
    {
        parent::setContent($document);
        $module = Icinga::app()->getModuleManager()->getModule('pdfexport');
        $activeScripts = file_get_contents($module->getJsDir() . '/activate-scripts.js');
        $this->driver->execute(Command::executeScript($activeScripts));
        $this->driver->execute(Command::executeScript('new Layout().apply();'));
    }

    protected function waitForPageLoad(): void
    {
        parent::waitForPageLoad();
        $this->driver->wait(ElementPresentCondition::byCssSelector('[data-layout-ready=yes]'));
    }

    protected function getChromeDeveloperTools(): ChromeDevTools
    {
        return $this->dcp ??= new ChromeDevTools($this->driver);
    }

    protected function getPrintParameters(PrintableHtmlDocument $document): array
    {
        return array_merge([
            'printBackground' => true,
            'transferMode'    => 'ReturnAsBase64',
        ], $document->getPrintParameters());
    }

    protected function printToPdf(array $printParameters): string
    {
        $devTools = $this->getChromeDeveloperTools();
        try {
            $devTools->execute(DevToolsCommand::enableConsole());
        } catch (Exception) {
            // Deprecated, might fail
        }

        $result = $devTools->execute(DevToolsCommand::printToPdf($printParameters));

        return base64_decode($result['data']);
    }
}
