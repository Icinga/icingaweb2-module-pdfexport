<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * Represents a WebDriver response.
 */
readonly class Response
{
    /**
     * Create a new response.
     * @param string $sessionId the session ID of the response
     * @param int $status a http status code for the response
     * @param mixed|null $value the response value of the response
     */
    public function __construct(
        public string $sessionId,
        public int $status = 0,
        public mixed $value = null,
    ) {
    }
}
