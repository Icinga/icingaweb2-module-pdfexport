<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Icinga\Module\Pdfexport\PrintableHtmlDocument;

interface PfdPrintBackend
{
    public function toPdf(PrintableHtmlDocument $document): string;

    public function isSupported(): bool;

    public function close(): void;
}
