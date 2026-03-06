<?php

/* Icinga PDF Export | (c) 2026 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Pdfexport\Backend;

use Icinga\Module\Pdfexport\PrintableHtmlDocument;

interface PfdPrintBackend
{
    function toPdf(PrintableHtmlDocument $document): string;

    function isSupported(): bool;
}
