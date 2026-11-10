<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Exception;
use Icinga\Application\Icinga;
use Icinga\Module\Pdfexport\ChromeDevTools\ChromeDevTools;
use Icinga\Module\Pdfexport\ChromeDevTools\Command as DevToolsCommand;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\WebDriver\Capabilities;
use Icinga\Module\Pdfexport\WebDriver\Command;
use Icinga\Module\Pdfexport\WebDriver\ElementPresentCondition;

/**
 * Chrome PDF renderer with DevTools printing and document layout support
 */
class Chromedriver extends WebdriverBackend
{
    protected ?ChromeDevTools $dcp = null;

    /**
     * Create a new Chromedriver
     *
     * @param string $url ChromeDriver server URL
     */
    public function __construct(string $url)
    {
        parent::__construct($url, Capabilities::chrome());
    }

    /**
     * Replace the document and activate its layout scripts
     *
     * @param PrintableHtmlDocument $document Document to render
     *
     * @return void
     */
    protected function setContent(PrintableHtmlDocument $document): void
    {
        parent::setContent($document);
        $module = Icinga::app()->getModuleManager()->getModule('pdfexport');
        $activeScripts = file_get_contents($module->getJsDir() . '/activate-scripts.js');
        $this->driver->execute(Command::executeScript($activeScripts));
        $this->driver->execute(Command::executeScript('new Layout().apply();'));
    }

    /**
     * Wait for the document layout to finish
     *
     * @return void
     */
    protected function waitForPageLoad(): void
    {
        parent::waitForPageLoad();
        $this->driver->wait(ElementPresentCondition::byCssSelector('[data-layout-ready=yes]'));
    }

    /**
     * Get the DevTools connection for this browser session
     *
     * @return ChromeDevTools
     */
    protected function getChromeDeveloperTools(): ChromeDevTools
    {
        return $this->dcp ??= new ChromeDevTools($this->driver);
    }

    /**
     * Get the DevTools print options with background printing enabled
     *
     * @param PrintableHtmlDocument $document Document with print settings
     *
     * @return array<string, mixed>
     */
    protected function getPrintParameters(PrintableHtmlDocument $document): array
    {
        return array_merge([
            'printBackground' => true,
            'transferMode'    => 'ReturnAsBase64',
        ], $document->getPrintParameters());
    }

    /**
     * Print the current page through DevTools
     *
     * @param array<string, mixed> $printParameters DevTools print options
     *
     * @return string PDF contents
     */
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
