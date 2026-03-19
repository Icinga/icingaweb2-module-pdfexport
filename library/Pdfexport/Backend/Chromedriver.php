<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Exception;
use Icinga\Module\Pdfexport\ChromeDevTools\ChromeDevTools;
use Icinga\Module\Pdfexport\ChromeDevTools\Command as DevToolsCommand;
use Icinga\Module\Pdfexport\WebDriver\Command;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\WebDriver\Capabilities;
use Icinga\Module\Pdfexport\WebDriver\ElementPresentCondition;

class Chromedriver extends WebdriverBackend
{
    protected ?ChromeDevTools $dcp = null;

    public const ACTIVATE_SCRIPTS = <<<JS
function activateScripts(node) {
    if (isScript(node) === true) {
        node.parentNode.replaceChild(cloneScript(node) , node);
    } else {
        var i = -1, children = node.childNodes;
        while (++i < children.length) {
              activateScripts(children[i]);
        }
    }

    return node;
}

function cloneScript(node) {
    var script  = document.createElement("script");
    script.text = node.innerHTML;

    var i = -1, attrs = node.attributes, attr;
    while (++i < attrs.length) {                                    
          script.setAttribute((attr = attrs[i]).name, attr.value);
    }
    return script;
}

function isScript(node) {
    return node.tagName === 'SCRIPT';
}

activateScripts(document.documentElement);
JS;

    public function __construct(string $url)
    {
        parent::__construct($url, Capabilities::chrome());
    }

    protected function setContent(PrintableHtmlDocument $document): void
    {
        parent::setContent($document);

        $this->driver->execute(
            Command::executeScript(self::ACTIVATE_SCRIPTS),
        );
        $this->driver->execute(
            Command::executeScript('new Layout().apply();'),
        );
    }

    protected function waitForPageLoad(): void
    {
        parent::waitForPageLoad();

        $this->driver->wait(ElementPresentCondition::byCssSelector('[data-layout-ready=yes]'));
    }

    protected function getChromeDeveloperTools(): ChromeDevTools
    {
        if ($this->dcp === null) {
            $this->dcp = new ChromeDevTools($this->driver);
        }
        return $this->dcp;
    }

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
            $devTools->execute(DevToolsCommand::enableConsole());
        } catch (Exception $_) {
            // Deprecated, might fail
        }

        $result = $devTools->execute(DevToolsCommand::printToPdf($printParameters));

        return base64_decode($result['data']);
    }
}
