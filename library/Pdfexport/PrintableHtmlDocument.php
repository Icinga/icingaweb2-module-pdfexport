<?php

/* Icinga PDF Export | (c) 2019 Icinga GmbH | GPLv2 */

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

class PrintableHtmlDocument extends BaseHtmlElement
{
    /** @var string */
    const DEFAULT_HEADER_FOOTER_STYLE = <<<'CSS'
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

    /** @var string Document title */
    protected $title;

    /**
     * Paper orientation
     *
     * Defaults to false.
     *
     * @var ?bool
     */
    protected $landscape;

    /**
     * Print background graphics
     *
     * Defaults to false.
     *
     * @var ?bool
     */
    protected $printBackground;

    /**
     * Scale of the webpage rendering
     *
     * Defaults to 1.
     *
     * @var ?float
     */
    protected $scale;

    /**
     * Paper width in inches
     *
     * Defaults to 8.5 inches.
     *
     * @var ?float
     */
    protected $paperWidth;

    /**
     * Paper height in inches
     *
     * Defaults to 11 inches.
     *
     * @var ?float
     */
    protected $paperHeight;

    /**
     * Top margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     *
     * @var ?float
     */
    protected $marginTop;

    /**
     * Bottom margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     *
     * @var ?float
     */
    protected $marginBottom;

    /**
     * Left margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     *
     * @var ?float
     */
    protected $marginLeft;

    /**
     * Right margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     *
     * @var ?float
     */
    protected $marginRight;

    /**
     * Paper ranges to print, e.g., '1-5, 8, 11-13'
     *
     * Defaults to the empty string, which means print all pages
     *
     * @var ?string
     */
    protected $pageRanges;

    /**
     * Page height in pixels
     *
     * Minus the default vertical margins, this is 1035.
     * If the vertical margins are zero, it's 1160.
     * Whether there's a header or footer doesn't matter in any case.
     *
     * @var int
     */
    protected $pagePixelHeight = 1035;

    /**
     * HTML template for the print header
     *
     * Should be valid HTML markup with following classes used to inject printing values into them:
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
     *
     * @var ?ValidHtml
     */
    protected $headerTemplate;

    /**
     * HTML template for the print footer
     *
     * Should be valid HTML markup with following classes used to inject printing values into them:
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
     *
     * @var ?ValidHtml
     */
    protected $footerTemplate;

    /**
     * HTML for the cover page
     *
     * @var ValidHtml
     */
    protected $coverPage;

    /**
     * Whether or not to prefer page size as defined by css
     *
     * Defaults to false, in which case the content will be scaled to fit the paper size.
     *
     * @var ?bool
     */
    protected $preferCSSPageSize;

    protected $tag = 'body';

    /**
     * Get the document title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set the document title
     *
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title)
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
    public function setHeader(ValidHtml $header)
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
    public function setFooter(ValidHtml $footer)
    {
        $this->footerTemplate = $footer;

        return $this;
    }

    /**
     * Get the cover page
     *
     * @return ValidHtml|null
     */
    public function getCoverPage()
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
    public function setCoverPage(ValidHtml $coverPage)
    {
        $this->coverPage = $coverPage;

        return $this;
    }

    /**
     * Remove page margins
     *
     * @return $this
     */
    public function removeMargins()
    {
        $this->marginBottom = 0;
        $this->marginLeft = 0;
        $this->marginRight = 0;
        $this->marginTop = 0;

        return $this;
    }

    /**
     * Finalize document to be printed
     */
    protected function assemble()
    {
        $this->setWrapper(new HtmlElement(
            'html',
            null,
            new HtmlElement(
                'head',
                null,
                new HtmlElement(
                    'title',
                    null,
                    Text::create($this->title)
                ),
                $this->createStylesheet(),
                $this->createLayoutScript()
            )
        ));

        $this->getAttributes()->registerAttributeCallback('data-content-height', function () {
            return $this->pagePixelHeight;
        });
        $this->getAttributes()->registerAttributeCallback('style', function () {
            return sprintf('width: %sin;', $this->paperWidth ?: 8.5);
        });
    }

    /**
     * Get the parameters for Page.printToPDF
     *
     * @return array
     */
    public function getPrintParameters()
    {
        $parameters = [];

        if (isset($this->landscape)) {
            $parameters['landscape'] = $this->landscape;
        }

        if (isset($this->printBackground)) {
            $parameters['printBackground'] = $this->printBackground;
        }

        if (isset($this->scale)) {
            $parameters['scale'] = $this->scale;
        }

        if (isset($this->paperWidth)) {
            $parameters['paperWidth'] = $this->paperWidth;
        }

        if (isset($this->paperHeight)) {
            $parameters['paperHeight'] = $this->paperHeight;
        }

        if (isset($this->marginTop)) {
            $parameters['marginTop'] = $this->marginTop;
        }

        if (isset($this->marginBottom)) {
            $parameters['marginBottom'] = $this->marginBottom;
        }

        if (isset($this->marginLeft)) {
            $parameters['marginLeft'] = $this->marginLeft;
        }

        if (isset($this->marginRight)) {
            $parameters['marginRight'] = $this->marginRight;
        }

        if (isset($this->pageRanges)) {
            $parameters['pageRanges'] = $this->pageRanges;
        }

        if (isset($this->headerTemplate)) {
            $parameters['headerTemplate'] = $this->createHeader()->render();
            $parameters['displayHeaderFooter'] = true;

            if (! isset($parameters['marginTop'])) {
                $parameters['marginTop'] = 0.6;
            }
        } else {
            $parameters['headerTemplate'] = ' ';  // An empty string is ignored
        }

        if (isset($this->footerTemplate)) {
            $parameters['footerTemplate'] = $this->createFooter()->render();
            $parameters['displayHeaderFooter'] = true;

            if (! isset($parameters['marginBottom'])) {
                $parameters['marginBottom'] = 0.6;
            }
        } else {
            $parameters['footerTemplate'] = ' ';  // An empty string is ignored
        }

        if (isset($this->preferCSSPageSize)) {
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
            function ($matches) use ($app) {
                if (substr($matches[1], 0, 3) !== '../') {
                    return $matches[1];
                }

                $path = substr($matches[1], 3);
                if (substr($path, 0, 4) === 'lib/') {
                    $assetPath = substr($path, 4);

                    $library = null;
                    foreach ($app->getLibraries() as $candidate) {
                        if (substr($assetPath, 0, strlen($candidate->getName())) === $candidate->getName()) {
                            $library = $candidate;
                            $assetPath = ltrim(substr($assetPath, strlen($candidate->getName())), '/');
                            break;
                        }
                    }

                    if ($library === null) {
                        return $matches[1];
                    }

                    $path = $library->getStaticAssetPath() . DIRECTORY_SEPARATOR . $assetPath;
                } elseif (substr($matches[1], 0, 14) === '../static/img?') {
                    list($_, $query) = explode('?', $matches[1], 2);
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
            (new PrintStyleSheet())->render(true)
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
        if (! method_exists($module, 'getJsDir')) {
            $jsPath = join(DIRECTORY_SEPARATOR, [$module->getBaseDir(), 'public', 'js']);
        } else {
            $jsPath = $module->getJsDir();
        }

        $layoutJS = file_get_contents($jsPath . '/layout.js') . "\n\n\n";
        $layoutJS .= file_get_contents($jsPath . '/layout-plugins/page-breaker.js') . "\n\n\n";

        return new HtmlElement(
            'script',
            Attributes::create(['type' => 'application/javascript']),
            HtmlString::create($layoutJS)
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
                    static::DEFAULT_HEADER_FOOTER_STYLE
                )),
                new HtmlElement('header', null, $this->headerTemplate)
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
                    static::DEFAULT_HEADER_FOOTER_STYLE
                )),
                new HtmlElement('footer', null, $this->footerTemplate)
            );
    }
}
