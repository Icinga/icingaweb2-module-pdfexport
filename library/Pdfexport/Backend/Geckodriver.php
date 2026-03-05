<?php

namespace Icinga\Module\Pdfexport\Backend;

use Facebook\WebDriver\Remote\DesiredCapabilities;

class Geckodriver extends WebdriverBackend
{
    public function __construct(string $rul)
    {
        parent::__construct($rul, DesiredCapabilities::firefox());
    }
}
