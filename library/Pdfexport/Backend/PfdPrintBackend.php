<?php

namespace Icinga\Module\Pdfexport\Backend;

use Icinga\Module\Pdfexport\PrintableHtmlDocument;

interface PfdPrintBackend
{
    function toPdf(PrintableHtmlDocument $document): string;

    function isSupported(): bool;
}
