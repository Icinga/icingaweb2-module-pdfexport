<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Exception;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Icinga\Application\Logger;
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
            'transferMode'    => 'ReturnAsBase64',
        ];

        return array_merge(
            $parameters,
            $document->getPrintParameters(),
        );
    }

    protected function printToPdf(array $printParameters): string
    {
        $devTools = $this->getChromeDeveloperTools();

        $png = base64_decode($devTools->execute(
            'Page.captureScreenshot',
            [
                'format' => 'png',
            ],
        )['data']);

        $path = '/tmp/png-' . time() . '.png';
        file_put_contents($path, $png);
        Logger::debug("Wrote PNG: " . $path);

        try {
            $devTools->execute('Console.enable');
        } catch (Exception $_) {
            // Deprecated, might fail
        }

        $result = $devTools->execute(
            'Page.printToPDF',
            $printParameters,
        );

        return base64_decode($result['data']);
    }
}
