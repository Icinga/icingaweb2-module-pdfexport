<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport;

use Icinga\Application\Icinga;
use Icinga\Web\UrlParams;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Html\HtmlString;
use ipl\Html\Text;
use ipl\Html\ValidHtml;

/**
 * HTML document with page layout and browser print settings
 */
class PrintableHtmlDocument extends BaseHtmlElement
{
    /** @var string */
    public const DEFAULT_HEADER_FOOTER_STYLE = <<<'CSS'
        @font-face {
          font-family: "Helvetica Neue", "Helvetica", "Arial", sans-serif;
        }

        header, footer {
          width: 100%;
          height: 21px;
          font-size: 7px;
          display: flex;
          justify-content: space-between;
          margin-left: 0.75cm;
          margin-right: 0.75cm;
        }

        header > *:not(:last-child),
        footer > *:not(:last-child) {
          margin-right: 10px;
        }

        span {
          line-height: 7px;
          white-space: nowrap;
        }

        p {
          margin: 0;
          line-height: 7px;
          word-break: break-word;
          text-overflow: ellipsis;
          overflow: hidden;
          display: -webkit-box;
          -webkit-line-clamp: 3;
          -webkit-box-orient: vertical;
        }
        CSS;

    /** Document title */
    protected ?string $title = null;

    /**
     * Paper orientation
     *
     * Defaults to false.
     */
    protected ?bool $landscape = null;

    /**
     * Print background graphics
     *
     * Defaults to false.
     */
    protected ?bool $printBackground = null;

    /**
     * Scale of the webpage rendering
     *
     * Defaults to 1.
     */
    protected ?float $scale = null;

    /**
     * Paper width in inches
     *
     * Defaults to 8.5 inches.
     */
    protected ?float $paperWidth = null;

    /**
     * Paper height in inches
     *
     * Defaults to 11 inches.
     */
    protected ?float $paperHeight = null;

    /**
     * Top margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     */
    protected ?float $marginTop = null;

    /**
     * Bottom margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     */
    protected ?float $marginBottom = null;

    /**
     * Left margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     */
    protected ?float $marginLeft = null;

    /**
     * Right margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     */
    protected ?float $marginRight = null;

    /**
     * Paper ranges to print, e.g., '1-5, 8, 11-13'
     *
     * Defaults to the empty string, which means print all pages
     *
     * @var string
     */
    protected string $pageRanges = "";

    /**
     * Page height in pixels
     *
     * Minus the default vertical margins, this is 1035.
     * If the vertical margins are zero, it's 1160.
     * Whether there's a header or footer doesn't matter in any case.
     */
    protected int $pagePixelHeight = 1035;

    /**
     * HTML template for the print header
     *
     * Should be valid HTML markup with the following classes used to inject printing values into them:
     *   * date: formatted print date
     *   * title: document title
     *   * url: document location
     *   * pageNumber: current page number
     *   * totalPages: total pages in the document
     *
     * For example, `<span class=title></span>` would generate span containing the title.
     *
     * Note that the header cannot exceed a height of 21px regardless of the margin's height or document's scale.
     * With the default style, this height is separated by three lines, each accommodating 7px.
     * Use `span`'s for single line text and `p`'s for multiline text.
     */
    protected ?ValidHtml $headerTemplate = null;

    /**
     * HTML template for the print footer
     *
     * Should be valid HTML markup with the following classes used to inject printing values into them:
     *   * date: formatted print date
     *   * title: document title
     *   * url: document location
     *   * pageNumber: current page number
     *   * totalPages: total pages in the document
     *
     * For example, `<span class=title></span>` would generate span containing the title.
     *
     * Note that the footer cannot exceed a height of 21px regardless of the margin's height or document's scale.
     * With the default style, this height is separated by three lines, each accommodating 7px.
     * Use `span`'s for single line text and `p`'s for multiline text.
     */
    protected ?ValidHtml $footerTemplate = null;

    /**
     * HTML for the cover page
     */
    protected ?ValidHtml $coverPage = null;

    /**
     * Whether to prefer page size as defined by CSS
     *
     * Defaults to false, in which case the content will be scaled to fit the paper size.
     */
    protected ?bool $preferCSSPageSize = null;

    protected $tag = 'body';

    /**
     * Get the document title
     *
     * @return ?string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set the document title
     *
     * @param ?string $title
     *
     * @return $this
     */
    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set page header
     *
     * @param ValidHtml $header
     *
     * @return $this
     */
    public function setHeader(ValidHtml $header): static
    {
        $this->headerTemplate = $header;

        return $this;
    }

    /**
     * Set page footer
     *
     * @param ValidHtml $footer
     *
     * @return $this
     */
    public function setFooter(ValidHtml $footer): static
    {
        $this->footerTemplate = $footer;

        return $this;
    }

    /**
     * Get the cover page
     *
     * @return ?ValidHtml
     */
    public function getCoverPage(): ?ValidHtml
    {
        return $this->coverPage;
    }

    /**
     * Set cover page
     *
     * @param ValidHtml $coverPage
     *
     * @return $this
     */
    public function setCoverPage(ValidHtml $coverPage): static
    {
        $this->coverPage = $coverPage;

        return $this;
    }

    /**
     * Remove page margins
     *
     * @return $this
     */
    public function removeMargins(): static
    {
        $this->marginBottom = 0;
        $this->marginLeft = 0;
        $this->marginRight = 0;
        $this->marginTop = 0;

        return $this;
    }

    /**
     * Finalize document to be printed
     *
     * @return void
     */
    protected function assemble(): void
    {
        $this->setWrapper(new HtmlElement(
            'html',
            null,
            new HtmlElement(
                'head',
                null,
                HtmlElement::create('meta', ['charset' => 'UTF-8']),
                new HtmlElement(
                    'title',
                    null,
                    Text::create($this->title),
                ),
                $this->createStylesheet(),
                $this->createLayoutScript(),
            ),
        ));

        $this->getAttributes()->registerAttributeCallback('data-content-height', fn(): int => $this->pagePixelHeight);
        $this->getAttributes()->registerAttributeCallback(
            'style',
            fn(): string => sprintf('width: %sin;', $this->paperWidth ?: 8.5),
        );
    }

    /**
     * Get W3C WebDriver print parameters with dimensions in centimeters
     *
     * Header and footer templates are unavailable in the W3C print command.
     *
     * @return array{
     *   orientation?: 'landscape'|'portrait',
     *   background?: bool,
     *   scale?: float,
     *   page?: array{width?: float, height?: float},
     *   margin?: array{top?: float, bottom?: float, left?: float, right?: float},
     *   pageRanges?: list<string>,
     *   shrinkToFit?: bool
     * }
     */
    public function getPrintParametersForWebdriver(): array
    {
        $parameters = [];

        if ($this->landscape !== null) {
            $parameters['orientation'] = $this->landscape ? 'landscape' : 'portrait';
        }

        if ($this->printBackground !== null) {
            $parameters['background'] = $this->printBackground;
        }

        if ($this->scale !== null) {
            $parameters['scale'] = $this->scale;
        }

        // Document dimensions use inches. WebDriver print dimensions use centimeters.
        if ($this->paperWidth !== null) {
            $parameters['page']['width'] = $this->paperWidth * 2.54;
        }

        if ($this->paperHeight !== null) {
            $parameters['page']['height'] = $this->paperHeight * 2.54;
        }

        if ($this->marginTop !== null) {
            $parameters['margin']['top'] = $this->marginTop * 2.54;
        }

        if ($this->marginBottom !== null) {
            $parameters['margin']['bottom'] = $this->marginBottom * 2.54;
        }

        if ($this->marginLeft !== null) {
            $parameters['margin']['left'] = $this->marginLeft * 2.54;
        }

        if ($this->marginRight !== null) {
            $parameters['margin']['right'] = $this->marginRight * 2.54;
        }

        if ($this->pageRanges !== '') {
            $parameters['pageRanges'] = array_map(trim(...), explode(',', $this->pageRanges));
        }

        if ($this->preferCSSPageSize !== null) {
            $parameters['shrinkToFit'] = ! $this->preferCSSPageSize;
        }

        return $parameters;
    }

    /**
     * Get the parameters for Page.printToPDF
     *
     * Omitting page ranges prints all pages.
     *
     * @return array{
     *   landscape?: bool,
     *   printBackground?: bool,
     *   scale?: float,
     *   paperWidth?: float,
     *   paperHeight?: float,
     *   marginTop?: float,
     *   marginBottom?: float,
     *   marginLeft?: float,
     *   marginRight?: float,
     *   pageRanges?: string,
     *   headerTemplate: string,
     *   footerTemplate: string,
     *   displayHeaderFooter?: bool,
     *   preferCSSPageSize?: bool
     * }
     */
    public function getPrintParameters(): array
    {
        $parameters = [];

        if ($this->landscape !== null) {
            $parameters['landscape'] = $this->landscape;
        }

        if ($this->printBackground !== null) {
            $parameters['printBackground'] = $this->printBackground;
        }

        if ($this->scale !== null) {
            $parameters['scale'] = $this->scale;
        }

        if ($this->paperWidth !== null) {
            $parameters['paperWidth'] = $this->paperWidth;
        }

        if ($this->paperHeight !== null) {
            $parameters['paperHeight'] = $this->paperHeight;
        }

        if ($this->marginTop !== null) {
            $parameters['marginTop'] = $this->marginTop;
        }

        if ($this->marginBottom !== null) {
            $parameters['marginBottom'] = $this->marginBottom;
        }

        if ($this->marginLeft !== null) {
            $parameters['marginLeft'] = $this->marginLeft;
        }

        if ($this->marginRight !== null) {
            $parameters['marginRight'] = $this->marginRight;
        }

        if ($this->pageRanges !== '') {
            $parameters['pageRanges'] = $this->pageRanges;
        }

        if ($this->headerTemplate !== null) {
            $parameters['headerTemplate'] = $this->createHeader()->render();
            $parameters['displayHeaderFooter'] = true;

            $parameters['marginTop'] ??= 0.6;
        } else {
            $parameters['headerTemplate'] = ' ';  // An empty string is ignored
        }

        if ($this->footerTemplate !== null) {
            $parameters['footerTemplate'] = $this->createFooter()->render();
            $parameters['displayHeaderFooter'] = true;

            $parameters['marginBottom'] ??= 0.6;
        } else {
            $parameters['footerTemplate'] = ' ';  // An empty string is ignored
        }

        if ($this->preferCSSPageSize !== null) {
            $parameters['preferCSSPageSize'] = $this->preferCSSPageSize;
        }

        return $parameters;
    }

    /**
     * Create CSS stylesheet
     *
     * @return ValidHtml
     */
    protected function createStylesheet(): ValidHtml
    {
        $app = Icinga::app();

        $css = preg_replace_callback(
            '~(?<=url\()[\'"]?([^(\'"]*)[\'"]?(?=\))~',
            function (array $matches) use ($app): string {
                if (! str_starts_with($matches[1], '../')) {
                    return $matches[1];
                }

                $path = substr($matches[1], 3);
                if (str_starts_with($path, 'lib/')) {
                    $assetPath = substr($path, 4);

                    $library = null;
                    foreach ($app->getLibraries() as $candidate) {
                        if (str_starts_with($assetPath, $candidate->getName())) {
                            $library = $candidate;
                            $assetPath = ltrim(substr($assetPath, strlen($candidate->getName())), '/');
                            break;
                        }
                    }

                    if ($library === null) {
                        return $matches[1];
                    }

                    $path = $library->getStaticAssetPath() . DIRECTORY_SEPARATOR . $assetPath;
                } elseif (str_starts_with($matches[1], '../static/img?')) {
                    [$_, $query] = explode('?', $matches[1], 2);
                    $params = UrlParams::fromQueryString($query);
                    if (! $app->getModuleManager()->hasEnabled($params->get('module_name'))) {
                        return $matches[1];
                    }

                    $module = $app->getModuleManager()->getModule($params->get('module_name'));
                    $imgRoot = $module->getBaseDir() . '/public/img/';
                    $path = realpath($imgRoot . $params->get('file'));
                } else {
                    $path = $app->getBootstrapDirectory() . '/' . $path;
                }

                if (! $path || ! file_exists($path) || ! is_file($path)) {
                    return $matches[1];
                }

                $mimeType = @mime_content_type($path);
                if ($mimeType === false) {
                    return $matches[1];
                }

                $fileContent = @file_get_contents($path);
                if ($fileContent === false) {
                    return $matches[1];
                }

                return "'data:$mimeType; base64, " . base64_encode($fileContent) . "'";
            },
            (new PrintStyleSheet())->render(true),
        );

        return new HtmlElement('style', null, HtmlString::create($css));
    }

    /**
     * Create layout javascript
     *
     * @return ValidHtml
     */
    protected function createLayoutScript(): ValidHtml
    {
        $module = Icinga::app()->getModuleManager()->getModule('pdfexport');

        $jsPath = $module->getJsDir();
        $layoutJS = file_get_contents($jsPath . '/layout.js') . "\n\n\n";
        $layoutJS .= file_get_contents($jsPath . '/layout-plugins/page-breaker.js') . "\n\n\n";

        return new HtmlElement(
            'script',
            Attributes::create(['type' => 'application/javascript']),
            HtmlString::create($layoutJS),
        );
    }

    /**
     * Create document header
     *
     * @return ValidHtml
     */
    protected function createHeader(): ValidHtml
    {
        return (new HtmlDocument())
            ->addHtml(
                new HtmlElement('style', null, HtmlString::create(
                    static::DEFAULT_HEADER_FOOTER_STYLE,
                )),
                new HtmlElement('header', null, $this->headerTemplate),
            );
    }

    /**
     * Create document footer
     *
     * @return ValidHtml
     */
    protected function createFooter(): ValidHtml
    {
        return (new HtmlDocument())
            ->addHtml(
                new HtmlElement('style', null, HtmlString::create(
                    static::DEFAULT_HEADER_FOOTER_STYLE,
                )),
                new HtmlElement('footer', null, $this->footerTemplate),
            );
    }
}
