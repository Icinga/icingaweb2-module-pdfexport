<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport;

use DOMDocument;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use ipl\Html\ValidHtml;
use PHPUnit\Framework\TestCase;

class PrintableHtmlDocumentTest extends TestCase
{
    public function testRenderedDocumentDeclaresUtf8BeforeLargeTitlesAndPreservesUnicode(): void
    {
        $document = new class extends PrintableHtmlDocument {
            protected function createStylesheet(): ValidHtml
            {
                return HtmlString::create('');
            }

            protected function createLayoutScript(): ValidHtml
            {
                return HtmlString::create('');
            }
        };
        $document->setTitle(str_repeat('Availability report ', 80));
        $document->setContent(Html::tag('p', 'Grüße 日本語 😀'));

        $html = $document->render();

        $this->assertStringContainsString('charset="UTF-8"', substr($html, 0, 1024));
        $parsed = new DOMDocument();
        $this->assertTrue($parsed->loadHTML($html));
        $this->assertSame('Grüße 日本語 😀', $parsed->getElementsByTagName('p')->item(0)->textContent);
    }

    public function testDefaultWebDriverOptionsPrintAllPages(): void
    {
        $this->assertSame([], (new PrintableHtmlDocument())->getPrintParametersForWebdriver());
    }

    public function testWebDriverDimensionsUseCentimetersAndNestedProtocolOptions(): void
    {
        $document = $this->createCustomDocument();

        $this->assertEqualsWithDelta([
            'orientation' => 'landscape',
            'background' => true,
            'scale' => 0.8,
            'page' => ['width' => 21.59, 'height' => 27.94],
            'margin' => ['top' => 1.27, 'bottom' => 0.635, 'left' => 1.905, 'right' => 2.54],
            'pageRanges' => ['1-3', '5', '8-10'],
            'shrinkToFit' => false,
        ], $document->getPrintParametersForWebdriver(), 0.000001);
    }

    public function testRemovingMarginsKeepsExplicitZerosInWebDriverOptions(): void
    {
        $parameters = (new PrintableHtmlDocument())->removeMargins()->getPrintParametersForWebdriver();

        $this->assertEquals(['top' => 0, 'bottom' => 0, 'left' => 0, 'right' => 0], $parameters['margin']);
    }

    public function testCdpOptionsKeepInchesAndTheirOwnSchema(): void
    {
        $parameters = $this->createCustomDocument()->getPrintParameters();

        $this->assertTrue($parameters['landscape']);
        $this->assertSame(8.5, $parameters['paperWidth']);
        $this->assertSame(11.0, $parameters['paperHeight']);
        $this->assertSame(0.5, $parameters['marginTop']);
        $this->assertSame('1-3, 5, 8-10', $parameters['pageRanges']);
        $this->assertTrue($parameters['preferCSSPageSize']);
        $this->assertArrayNotHasKey('page', $parameters);
        $this->assertArrayNotHasKey('shrinkToFit', $parameters);
    }

    protected function createCustomDocument(): PrintableHtmlDocument
    {
        return new class extends PrintableHtmlDocument {
            public function __construct()
            {
                $this->landscape = true;
                $this->printBackground = true;
                $this->scale = 0.8;
                $this->paperWidth = 8.5;
                $this->paperHeight = 11.0;
                $this->marginTop = 0.5;
                $this->marginBottom = 0.25;
                $this->marginLeft = 0.75;
                $this->marginRight = 1.0;
                $this->pageRanges = '1-3, 5, 8-10';
                $this->preferCSSPageSize = true;
            }
        };
    }
}
