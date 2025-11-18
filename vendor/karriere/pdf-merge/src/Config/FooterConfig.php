<?php

namespace Karriere\PdfMerge\Config;

class FooterConfig
{
    /**
     * @param ?RGB $textColor RGB array color for text
     * @param ?RGB $lineColor RGB array color for line
     * @param int $margin minimum distance (in "user units") between footer and bottom page margin
     */
    public function __construct(
        private ?RGB $textColor = null,
        private ?RGB $lineColor = null,
        private int $margin = 0,
    ) {
    }

    public function textColor(): RGB
    {
        return $this->textColor ?: new RGB();
    }

    public function lineColor(): RGB
    {
        return $this->lineColor ?: new RGB();
    }

    public function margin(): int
    {
        return $this->margin;
    }
}
