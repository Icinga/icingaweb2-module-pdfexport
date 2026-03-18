<?php

namespace Icinga\Module\Pdfexport\WebDriver;

use Icinga\Module\Pdfexport\WebDriver\CommandInterface;

class CustomCommand implements CommandInterface
{
    public function __construct(
        protected string $method,
        protected string $path,
        protected array $parameters = [],
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
