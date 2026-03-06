<?php

/* Icinga PDF Export | (c) 2026 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Pdfexport\Backend;

use Facebook\WebDriver\Remote\DesiredCapabilities;

class Geckodriver extends WebdriverBackend
{
    public function __construct(string $rul)
    {
        parent::__construct($rul, DesiredCapabilities::firefox());
    }
}
