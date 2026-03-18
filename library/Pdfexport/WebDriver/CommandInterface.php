<?php

namespace Icinga\Module\Pdfexport\WebDriver;

interface CommandInterface
{
    public function getPath(): string;

    public function getMethod(): string;

    public function getParameters(): array;
}
