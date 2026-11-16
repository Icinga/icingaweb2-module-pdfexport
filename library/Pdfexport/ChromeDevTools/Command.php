<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\ChromeDevTools;

/**
 * A Chrome DevTools Protocol command
 *
 * Subclasses must accept the same constructor arguments so inherited factories
 * can create them.
 *
 * @phpstan-consistent-constructor
 */
readonly class Command
{
    /**
     * Create a new Command
     *
     * @param string $name
     * @param array<string, mixed> $parameters Named protocol parameters
     */
    public function __construct(
        public string $name,
        public array $parameters = [],
    ) {
    }

    /**
     * Enable console events
     *
     * @return static
     */
    public static function enableConsole(): static
    {
        return new static('Console.enable');
    }

    /**
     * Print the page as a PDF
     *
     * @param array<string, mixed> $printParameters Named print options
     *
     * @return static
     */
    public static function printToPdf(array $printParameters): static
    {
        return new static('Page.printToPDF', $printParameters);
    }
}
