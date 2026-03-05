<?php

namespace Icinga\Module\Pdfexport\Driver;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;

class Webdriver implements PfdPrintDriver
{
    protected RemoteWebDriver $driver;

    public function __construct(
        string $url,
        DesiredCapabilities $capabilities
    ) {
        $this->driver = RemoteWebDriver::create($url, $capabilities);
    }

    function __destruct()
    {
        $this->driver->quit();
    }

    protected function setContent(PrintableHtmlDocument $document): void
    {
        // This is horribly ugly, but it works for all browser backends
        $encoded = base64_encode($document);
        $this->driver->executeScript("document.body.innerHTML = atob('$encoded');");
    }

    protected function waitForPageLoad(): void
    {
        // Wait for the body element to ensure the page has fully loaded
        $wait = new WebDriverWait($this->driver, 10);
        $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body')));
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
        $result = $this->driver->executeCustomCommand(
            '/session/:sessionId/print',
            'POST',
            $printParameters,
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

    function isSupported(): bool
    {
        // TODO: Come up with a check
        return true;
    }
}
