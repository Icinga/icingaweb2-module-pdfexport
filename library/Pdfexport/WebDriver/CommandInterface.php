<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

interface CommandInterface
{
    public function getPath(): string;

    public function getMethod(): string;

    public function getParameters(): array;
}
