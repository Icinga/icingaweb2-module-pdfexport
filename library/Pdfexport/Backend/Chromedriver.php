<?php

namespace Icinga\Module\Pdfexport\Backend;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;

class Chromedriver extends WebdriverBackend
{
    protected ?ChromeDevToolsDriver $dcp = null;

    public function __construct(string $url)
    {
        parent::__construct($url, DesiredCapabilities::chrome());
    }

    protected function getChromeDeveloperTools(): ChromeDevToolsDriver
    {
        if ($this->dcp === null) {
            $this->dcp = new ChromeDevToolsDriver($this->driver);
        }
        return $this->dcp;
    }

//    protected function setContent(PrintableHtmlDocument $document): void
//    {
//        $devTools = $this->getChromeDeveloperTools();
//        $devTools->execute(
//            'Page.setDocumentContent',
//            [
//                'frameId' => 'TODO',
//                'html' => $document->render()
//            ]
//        );
//    }

    protected function getPrintParameters(PrintableHtmlDocument $document): array
    {
        $parameters = [
            'printBackground' => true,
        ];

        return array_merge(
            $parameters,
            $document->getPrintParameters(),
        );
    }

    protected function printToPdf(array $printParameters): string
    {
        $devTools = $this->getChromeDeveloperTools();
        $result = $devTools->execute(
            'Page.printToPDF',
            $printParameters,
        );

        return base64_decode($result['data']);
    }
}
