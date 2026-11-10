<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport;

use Icinga\Module\Pdfexport\ShellCommand;
use PHPUnit\Framework\TestCase;

class ShellCommandTest extends TestCase
{
    public function testStderrPipeIsAbsentBeforeStartupAndCanBeClosedRepeatedly(): void
    {
        $command = new ShellCommand('true');

        $this->assertNull($command->getStderrPipe());
        $command->closeStderrPipe();
        $command->closeStderrPipe();
        $this->assertNull($command->getStderrPipe());
        $this->assertSame('', $command->getStderr());
    }
}
