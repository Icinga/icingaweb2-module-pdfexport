<?php

namespace Icinga\Module\Pdfexport\Driver;

use Icinga\Module\Pdfexport\PrintableHtmlDocument;

interface PfdPrintDriver
{
    function toPdf(PrintableHtmlDocument $document): string;

    function isSupported(): bool;
}
