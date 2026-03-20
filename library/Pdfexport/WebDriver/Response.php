<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

class Response
{
    public function __construct(
        protected string $sessionId,
        protected int $status = 0,
        protected mixed $value = null,
    ) {
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
