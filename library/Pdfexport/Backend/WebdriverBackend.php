<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Exception;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\WebDriver\Capabilities;
use Icinga\Module\Pdfexport\WebDriver\ElementPresentCondition;
use Icinga\Module\Pdfexport\WebDriver\WebDriver;
use Icinga\Module\Pdfexport\WebDriver\Command;
use Throwable;

/**
 * Reusable PDF renderer backed by a WebDriver session
 *
 * Closing the backend ends its session permanently.
 */
class WebdriverBackend implements PfdPrintBackend
{
    protected WebDriver $driver;

    /**
     * Create a new WebdriverBackend
     *
     * @param string $url WebDriver server URL
     * @param Capabilities $capabilities Browser capabilities for the session
     */
    public function __construct(string $url, Capabilities $capabilities)
    {
        $this->driver = WebDriver::create($url, $capabilities);
    }

    /**
     * Release the session without propagating cleanup failures
     *
     * @return void
     */
    public function __destruct()
    {
        try {
            $this->close();
        } catch (Throwable) {
            // Destruction must preserve render failures and backend fallback.
        }
    }

    /**
     * Replace the current document while preserving Unicode text
     *
     * @param PrintableHtmlDocument $document Document to render
     *
     * @return void
     */
    protected function setContent(PrintableHtmlDocument $document): void
    {
        $this->driver->execute(Command::executeScript(
            'document.documentElement.innerHTML = arguments[0];',
            [$document->render()],
        ));
    }

    /**
     * Wait until the browser has a document body
     *
     * @return void
     */
    protected function waitForPageLoad(): void
    {
        $this->driver->wait(ElementPresentCondition::byTagName('body'));
    }

    /**
     * Get the WebDriver print options with background printing enabled
     *
     * @param PrintableHtmlDocument $document Document with print settings
     *
     * @return array<string, mixed>
     */
    protected function getPrintParameters(PrintableHtmlDocument $document): array
    {
        return array_merge(['background' => true], $document->getPrintParametersForWebdriver());
    }

    /**
     * Print the current page through the WebDriver print command
     *
     * @param array<string, mixed> $printParameters WebDriver print options
     *
     * @return string PDF contents
     */
    protected function printToPdf(array $printParameters): string
    {
        return base64_decode($this->driver->execute(Command::printPage($printParameters)));
    }

    public function toPdf(PrintableHtmlDocument $document): string
    {
        $this->setContent($document);
        $this->waitForPageLoad();

        return $this->printToPdf($this->getPrintParameters($document));
    }

    public function isSupported(): bool
    {
        try {
            return str_starts_with($this->printToPdf([]), '%PDF');
        } catch (Exception) {
            return false;
        }
    }

    /**
     * End the session and make this backend unusable for further rendering
     *
     * Repeated calls do nothing, including after a failed remote deletion.
     *
     * @return void
     *
     * @throws Exception If the remote session cannot be deleted
     */
    public function close(): void
    {
        $this->driver->quit();
    }

    public function supportsCoverPage(): bool
    {
        return true;
    }
}
