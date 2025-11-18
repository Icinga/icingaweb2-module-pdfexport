<?php

namespace Karriere\PdfMerge\Config;

class RGB
{
    public function __construct(private int $red = 0, private int $green = 0, private int $blue = 0)
    {
    }

    /**
     * @return array<int>
     */
    public function toArray(): array
    {
        return [$this->red, $this->green, $this->blue];
    }
}
