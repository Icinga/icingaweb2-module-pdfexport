<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * WebDriver response
 */
readonly class Response
{
    /**
     * Create a new Response
     *
     * @param string $sessionId The session ID of the response
     * @param int $status Legacy WebDriver status code, or zero for success
     * @param mixed|null $value Command-specific response value
     */
    public function __construct(
        public string $sessionId,
        public int $status = 0,
        public mixed $value = null,
    ) {
    }
}
