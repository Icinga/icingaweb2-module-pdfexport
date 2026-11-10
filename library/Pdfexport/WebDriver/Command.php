<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * A standard WebDriver command
 *
 * Subclasses must accept the same constructor arguments so inherited factories
 * can create them.
 *
 * @phpstan-consistent-constructor
 */
class Command implements CommandInterface
{
    /**
     * Create a new Command
     *
     * @param CommandName $name The standardized command instance
     * @param array<string, mixed> $parameters Named command parameters
     */
    public function __construct(
        protected CommandName $name,
        protected array $parameters = [],
    ) {
    }

    /**
     * Create a command to execute a script
     *
     * @param string $script The raw JavaScript to execute in the browser
     * @param array<mixed> $arguments The arguments to pass to the script
     *
     * @return static
     */
    public static function executeScript(string $script, array $arguments = []): static
    {
        $params = [
            'script' => $script,
            'args'   => static::prepareScriptArguments($arguments),
        ];

        return new static(CommandName::ExecuteScript, $params);
    }

    /**
     * Create a command to get the page source
     *
     * This command is useful for debugging purposes and will return the entire HTML source of the current page.
     *
     * @return static
     */
    public static function getPageSource(): static
    {
        return new static(CommandName::GetPageSource);
    }

    /**
     * Find an element on the page using the specified method and value
     *
     * @param string $method
     * @param string $value
     *
     * @return static
     */
    public static function findElement(string $method, string $value): static
    {
        return new static(CommandName::FindElement, [
            'using' => $method,
            'value' => $value,
        ]);
    }

    /**
     * Print a page using the specified print parameters
     *
     * The result of this command is a PDF file that is base64-encoded.
     *
     * @param array<string, mixed> $printParameters
     *
     * @return static
     */
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

    /**
     * Get the underlying standardized command name
     *
     * @return CommandName
     */
    public function getName(): CommandName
    {
        return $this->name;
    }

    /**
     * Format script arguments for use in ExecuteScript commands
     *
     * Nested arrays retain their keys and values for JSON serialization.
     *
     * @param array<mixed> $arguments
     *
     * @return array<mixed>
     */
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
}
