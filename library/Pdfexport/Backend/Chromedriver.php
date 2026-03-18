<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Exception;
use Icinga\Module\Pdfexport\ChromeDevTools\ChromeDevTools;
use Icinga\Module\Pdfexport\ChromeDevTools\Command;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\WebDriver\Capabilities;

class Chromedriver extends WebdriverBackend
{
    protected ?ChromeDevTools $dcp = null;

    public function __construct(string $url)
    {
        parent::__construct($url, Capabilities::chrome());
    }

    protected function getChromeDeveloperTools(): ChromeDevTools
    {
        if ($this->dcp === null) {
            $this->dcp = new ChromeDevTools($this->driver);
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

        try {
            $devTools->execute(Command::enableConsole());
        } catch (Exception $_) {
            // Deprecated, might fail
        }

        $result = $devTools->execute(Command::printToPdf($printParameters));

        return base64_decode($result['data']);
    }
}
