<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

class ElementPresentCondition implements ConditionInterface
{
    protected const WEBDRIVER_ELEMENT_IDENTIFIER = 'element-6066-11e4-a52e-4f735466cecf';

    protected function __construct(
        protected string $mechanism,
        protected string $value,
    ) {
    }

    public function apply(WebDriver $driver): bool
    {
        $response = $driver->execute(
            Command::findElement($this->mechanism, $this->value),
        );

        return isset($response['ELEMENT']) || isset($response[self::WEBDRIVER_ELEMENT_IDENTIFIER]);
    }

    public static function byCssSelector(string $selector): static
    {
        return new static('css selector', $selector);
    }

    public static function byLinkText(string $linkText): static
    {
        return new static('link text', $linkText);
    }

    public static function byPartialLinkText(string $partialLinkText): static
    {
        return new static('partial link text', $partialLinkText);
    }

    public static function byId(string $id): static
    {
        return static::byCssSelector('#' . $id);
    }

    public static function byClassName(string $className): static
    {
        return static::byCssSelector('.' . $className);
    }

    public static function byName(string $name): static
    {
        return static::byCssSelector('[name="' . $name . '"]');
    }

    public static function byTagName(string $tagName): static
    {
        return new static('tag name', $tagName);
    }

    public static function byXPath(string $xpath): static
    {
        return new static('xpath', $xpath);
    }
}
