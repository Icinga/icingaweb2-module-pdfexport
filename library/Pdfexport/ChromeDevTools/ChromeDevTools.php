<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\ChromeDevTools;

use Icinga\Module\Pdfexport\WebDriver\CustomCommand;
use Icinga\Module\Pdfexport\WebDriver\WebDriver;

/**
 * Chrome DevTools commands transported through a WebDriver session
 */
class ChromeDevTools
{
    /**
     * Create a new ChromeDevTools
     *
     * @param WebDriver $driver Active Chrome WebDriver session
     */
    public function __construct(
        protected readonly WebDriver $driver,
    ) {
    }

    /**
     * Execute a DevTools command with an object of protocol parameters
     *
     * @param Command $command Command to execute
     *
     * @return mixed Command-specific result
     */
    public function execute(Command $command): mixed
    {
        return $this->driver->execute(new CustomCommand('POST', '/session/:sessionId/goog/cdp/execute', [
            'cmd' => $command->name,
            'params' => (object) $command->parameters,
        ]));
    }
}
