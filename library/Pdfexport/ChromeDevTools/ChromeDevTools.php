<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\ChromeDevTools;

use Icinga\Module\Pdfexport\WebDriver\CustomCommand;
use Icinga\Module\Pdfexport\WebDriver\WebDriver;

class ChromeDevTools
{
    public function __construct(
        protected WebDriver $driver,
    ) {
    }

    public function execute(Command $command): mixed
    {
        return $this->driver->execute(new CustomCommand(
            'POST',
            '/session/:sessionId/goog/cdp/execute',
            [
                'cmd' => $command->name,
                'params' => $command->parameters,
            ],
        ));
    }
}
