<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * Interface for WebDriver commands.
 * @link https://www.w3.org/TR/webdriver/#commands
 */
interface CommandInterface
{
    /**
     * Get the path to the endpoint of the command.
     * @return string
     */
    public function getPath(): string;

    /**
     * The HTTP method to use for the command.
     * @return string
     */
    public function getMethod(): string;

    /**
     * Get the command parameters.
     * @return array
     */
    public function getParameters(): array;
}
