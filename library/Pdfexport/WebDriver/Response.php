<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

readonly class Response
{
    public function __construct(
        public string $sessionId,
        public int $status = 0,
        public mixed $value = null,
    ) {
    }
}
