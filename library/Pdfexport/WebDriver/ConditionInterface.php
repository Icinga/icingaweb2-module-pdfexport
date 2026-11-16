<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * Condition evaluated against a WebDriver session
 */
interface ConditionInterface
{
    /**
     * Check whether the condition is met
     *
     * @param WebDriver $driver The session to inspect
     *
     * @return bool
     */
    public function apply(WebDriver $driver): bool;
}
