<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Icinga\Module\Pdfexport\PrintableHtmlDocument;

interface PfdPrintBackend
{
    function toPdf(PrintableHtmlDocument $document): string;

    function isSupported(): bool;

    function close(): void;
}
