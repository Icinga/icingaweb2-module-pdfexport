<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * A custom WebDriver command for arbitrary browser requests
 *
 * These commands are outside the WebDriver protocol.
 */
class CustomCommand implements CommandInterface
{
    /**
     * Create a new CustomCommand
     *
     * @param string $method
     * @param string $path
     * @param array<string, mixed> $parameters
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
