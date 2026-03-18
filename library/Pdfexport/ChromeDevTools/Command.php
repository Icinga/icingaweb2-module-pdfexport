<?php

namespace Icinga\Module\Pdfexport\ChromeDevTools;

class Command
{
    public function __construct(
        protected string $name,
        protected array $parameters = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParameters(): array
    {
        return $this->parameters;
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
