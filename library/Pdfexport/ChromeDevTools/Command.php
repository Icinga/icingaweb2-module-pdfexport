<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\ChromeDevTools;

readonly class Command
{
    public function __construct(
        public string $name,
        public array $parameters = [],
    ) {
    }

    public static function enableConsole(): static
    {
        return new static('Console.enable');
    }

    public static function printToPdf(array $printParameters): static
    {
        return new static('Page.printToPDF', $printParameters);
    }
}
