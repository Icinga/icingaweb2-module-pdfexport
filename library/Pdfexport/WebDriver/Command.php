<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * Wrapper class for standard WebDriver commands.
 */
class Command implements CommandInterface
{
    /**
     * Create a new command.
     * @param CommandName $name the standardized command instance.
     * @param array $parameters the parameters for the command
     */
    public function __construct(
        protected CommandName $name,
        protected array $parameters = [],
    ) {
    }

    /**
     * Convenience method to create a new ExecuteScript command.
     * @param string $script the raw JavaScript to execute in the browser
     * @param array $arguments the arguments to pass to the script
     *
     * @return static
     */
    public static function executeScript(string $script, array $arguments = []): static
    {
        $params = [
            'script' => $script,
            'args' => static::prepareScriptArguments($arguments),
        ];

        return new static(CommandName::ExecuteScript, $params);
    }

    /**
     * Convenience method to create a new GetPageSource command.
     * This command is useful for debugging purposes and will return the entire HTML source of the current page.
     * @return static
     */
    public static function getPageSource(): static
    {
        return new static(CommandName::GetPageSource);
    }

    /**
     * Find an element on the page using the specified method and value.
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
     * Format script arguments for use in ExecuteScript commands.
     * This method recursively converts nested arrays into JSON objects.
     * @param array $arguments
     *
     * @return array
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

    /**
     * Print a page using the specified print parameters.
     * The result of this command is a PDF file that is base64-encoded.
     * @param array $printParameters
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
     * Return the underlying standardized command name.
     * @return CommandName
     */
    public function getName(): CommandName
    {
        return $this->name;
    }
}
