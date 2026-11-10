<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Icinga\Module\Pdfexport\WebDriver\Capabilities;

/**
 * Firefox PDF renderer using the WebDriver print command
 */
class Geckodriver extends WebdriverBackend
{
    /**
     * Create a new Geckodriver
     *
     * @param string $rul GeckoDriver server URL
     */
    public function __construct(string $rul)
    {
        parent::__construct($rul, Capabilities::firefox());
    }

    /**
     * Check whether the generated PDFs support merging with a cover page
     *
     * @return false Firefox PDFs use compression unsupported by the merger
     */
    public function supportsCoverPage(): bool
    {
        return false;
    }
}
