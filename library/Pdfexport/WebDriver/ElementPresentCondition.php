<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * An element lookup condition
 *
 * Subclasses must accept the same constructor arguments so inherited factories
 * can create them.
 *
 * @phpstan-consistent-constructor
 */
class ElementPresentCondition implements ConditionInterface
{
    /** @var string */
    protected const WEBDRIVER_ELEMENT_IDENTIFIER = 'element-6066-11e4-a52e-4f735466cecf';

    /**
     * Create a new ElementPresentCondition
     *
     * @param string $mechanism
     * @param string $value
     */
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

        return isset($response['ELEMENT']) || isset($response[static::WEBDRIVER_ELEMENT_IDENTIFIER]);
    }

    /**
     * Find an element by CSS selector
     *
     * @param string $selector
     *
     * @return static
     */
    public static function byCssSelector(string $selector): static
    {
        return new static('css selector', $selector);
    }

    /**
     * Find an element by link text
     *
     * @param string $linkText
     *
     * @return static
     */
    public static function byLinkText(string $linkText): static
    {
        return new static('link text', $linkText);
    }

    /**
     * Find an element by partial link text
     *
     * @param string $partialLinkText
     *
     * @return static
     */
    public static function byPartialLinkText(string $partialLinkText): static
    {
        return new static('partial link text', $partialLinkText);
    }

    /**
     * Find an element by ID
     *
     * @param string $id
     *
     * @return static
     */
    public static function byId(string $id): static
    {
        return static::byCssSelector('#' . $id);
    }

    /**
     * Find an element by class name
     *
     * @param string $className
     *
     * @return static
     */
    public static function byClassName(string $className): static
    {
        return static::byCssSelector('.' . $className);
    }

    /**
     * Find an element by name
     *
     * @param string $name
     *
     * @return static
     */
    public static function byName(string $name): static
    {
        return static::byCssSelector('[name="' . $name . '"]');
    }

    /**
     * Find an element by tag name
     *
     * @param string $tagName
     *
     * @return static
     */
    public static function byTagName(string $tagName): static
    {
        return new static('tag name', $tagName);
    }

    /**
     * Find an element by XPath
     *
     * @param string $xpath
     *
     * @return static
     */
    public static function byXPath(string $xpath): static
    {
        return new static('xpath', $xpath);
    }
}
