<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Icinga\Module\Pdfexport\WebDriver\Capabilities;

class Geckodriver extends WebdriverBackend
{
    public function __construct(string $rul)
    {
        parent::__construct($rul, Capabilities::firefox());
    }
}
