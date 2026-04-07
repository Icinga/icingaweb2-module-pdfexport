<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * Interface for conditions that can be used for searching elements in the browsers DOM.
 */
interface ConditionInterface
{
    /**
     * Apply the condition to the given WebDriver instance and return whether the condition is met.
     * @param WebDriver $driver the WebDriver instance to apply the condition to
     *
     * @return bool
     */
    public function apply(WebDriver $driver): bool;
}
