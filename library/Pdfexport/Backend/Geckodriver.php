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

    public function supportsCoverPage(): bool
    {
        // Firefox generates compressed PDFs, which can't be merged by the `tcpi` libary
        return false;
    }
}
