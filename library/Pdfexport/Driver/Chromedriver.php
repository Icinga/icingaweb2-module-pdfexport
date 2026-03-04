<?php

namespace Icinga\Module\Pdfexport\Driver;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;

class Chromedriver extends Webdriver
{
    public function __construct(string $url)
    {
        parent::__construct($url, DesiredCapabilities::chrome());
    }

    protected function setContent(PrintableHtmlDocument $document): void
    {
        // TODO: Replace with CDP
        parent::setContent($document);
    }

    protected function printToPdf(array $printParameters): string
    {
        // TODO: Implement
//        // This only works for chrome
//        $devTools = new ChromeDevToolsDriver($driver);
//        $result = $devTools->execute(
//            'Page.printToPDF',
//        );
//
//        return base64_decode($result['data']);
        return parent::printToPdf($printParameters);
    }
}
