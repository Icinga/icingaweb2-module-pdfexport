<?php

namespace Icinga\Module\Pdfexport\Driver;

use Facebook\WebDriver\Remote\DesiredCapabilities;

class Geckodriver extends Webdriver
{
    public function __construct(string $rul)
    {
        parent::__construct($rul, DesiredCapabilities::firefox());
    }
}
