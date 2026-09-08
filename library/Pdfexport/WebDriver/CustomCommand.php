<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * A custom WebDriver command that allows sending arbitrary requests to the browser.
 * These commands are not part of the WebDriver protocol and are not covered by the official documentation.
 */
class CustomCommand implements CommandInterface
{
    /**
     * Create a new custom command.
     * @param string $method
     * @param string $path
     * @param array $parameters
     */
    public function __construct(
        protected string $method,
        protected string $path,
        protected array $parameters = [],
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
