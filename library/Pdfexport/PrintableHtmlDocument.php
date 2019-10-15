<?php
// Icinga PDF Export | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Pdfexport;

use Icinga\Web\StyleSheet;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlString;
use ipl\Html\ValidHtml;

class PrintableHtmlDocument extends HtmlDocument
{
    /**
     * Paper orientation
     *
     * Defaults to false.
     *
     * @var bool
     */
    protected $landscape;

    /**
     * Print background graphics
     *
     * Defaults to false.
     *
     * @var bool
     */
    protected $printBackground;

    /**
     * Scale of the webpage rendering
     *
     * Defaults to 1.
     *
     * @var float
     */
    protected $scale;

    /**
     * Paper width in inches
     *
     * Defaults to 8.5 inches.
     *
     * @var float
     */
    protected $paperWidth;

    /**
     * Paper height in inches
     *
     * Defaults to 11 inches.
     *
     * @var float
     */
    protected $paperHeight;

    /**
     * Top margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     *
     * @var float
     */
    protected $marginTop;

    /**
     * Bottom margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     *
     * @var float
     */
    protected $marginBottom;

    /**
     * Left margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     *
     * @var float
     */
    protected $marginLeft;

    /**
     * Right margin in inches
     *
     * Defaults to 1cm (~0.4 inches).
     *
     * @var float
     */
    protected $marginRight;

    /**
     * Paper ranges to print, e.g., '1-5, 8, 11-13'
     *
     * Defaults to the empty string, which means print all pages
     *
     * @var string
     */
    protected $pageRanges;

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
     * @var ValidHtml
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
     * @var ValidHtml
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
     * @var bool
     */
    protected $preferCSSPageSize;

    /**
     * Set page header
     *
     * @param ValidHtml $header
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
     * @return $this
     */
    public function setCoverPage(ValidHtml $coverPage)
    {
        $this->coverPage = $coverPage;

        return $this;
    }

    /**
     * Finalize document to be printed
     */
    protected function assemble()
    {
        $head = Html::tag('head');
        $head->add(Html::tag(
            'style',
            null,
            new HtmlString(new StyleSheet())
        ));

        $html = Html::tag('html', null, $head);

        $body = Html::tag('body');
        $html->wrap($body);

        $this->setWrapper($body);
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
            $parameters['headerTemplate'] = $this->headerTemplate->render();
            $parameters['displayHeaderFooter'] = true;
        } else {
            $parameters['headerTemplate'] = ' ';  // An empty string is ignored
        }

        if (isset($this->footerTemplate)) {
            $parameters['footerTemplate'] = $this->footerTemplate->render();
            $parameters['displayHeaderFooter'] = true;
        } else {
            $parameters['footerTemplate'] = ' ';  // An empty string is ignored
        }

        if (isset($this->preferCSSPageSize)) {
            $parameters['preferCSSPageSize'] = $this->preferCSSPageSize;
        }

        return $parameters;
    }
}
