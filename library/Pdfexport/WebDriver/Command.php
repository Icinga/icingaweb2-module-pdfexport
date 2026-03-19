<?php

namespace Icinga\Module\Pdfexport\WebDriver;

class Command implements CommandInterface
{
    public function __construct(
        protected CommandName $name,
        protected array $parameters = [],
    ) {
    }

    public static function executeScript(string $script, array $arguments = []): static
    {
        $params = [
            'script' => $script,
            'args' => static::prepareScriptArguments($arguments),
        ];

        return new static(CommandName::ExecuteScript, $params);
    }

    public static function getPageSource(): static
    {
        return new static(CommandName::GetPageSource);
    }

    public static function findElement(string $method, string $value): static
    {
        return new static(CommandName::FindElement, [
            'using' => $method,
            'value' => $value,
        ]);
    }

    protected static function prepareScriptArguments(array $arguments): array
    {
        $args = [];
        foreach ($arguments as $key => $value) {
            if (is_array($value)) {
                $args[$key] = static::prepareScriptArguments($value);
            } else {
                $args[$key] = $value;
            }
        }

        return $args;
    }

    public static function printPage(array $printParameters): static
    {
        return new static(CommandName::PrintPage, $printParameters);
    }

    public function getPath(): string
    {
        return $this->name->getPath();
    }

    public function getMethod(): string
    {
        return $this->name->getMethod();
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getName(): CommandName
    {
        return $this->name;
    }
}
