<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport\ChromeDevTools;

use Icinga\Module\Pdfexport\ChromeDevTools\ChromeDevTools;
use Icinga\Module\Pdfexport\ChromeDevTools\Command;
use Icinga\Module\Pdfexport\WebDriver\CustomCommand;
use Icinga\Module\Pdfexport\WebDriver\WebDriver;
use PHPUnit\Framework\TestCase;

class ChromeDevToolsTest extends TestCase
{
    public function testEmptyParametersAreEncodedAsAnObject(): void
    {
        $driver = $this->createMock(WebDriver::class);
        $driver->expects($this->once())->method('execute')->willReturnCallback(
            function (CustomCommand $command): array {
                $this->assertSame('POST', $command->getMethod());
                $this->assertSame('/session/:sessionId/goog/cdp/execute', $command->getPath());
                $this->assertJsonStringEqualsJsonString(
                    '{"cmd":"Console.enable","params":{}}',
                    json_encode($command->getParameters(), JSON_THROW_ON_ERROR)
                );

                return [];
            }
        );

        $this->assertSame([], (new ChromeDevTools($driver))->execute(Command::enableConsole()));
    }

    public function testNonemptyParametersRetainTheirNamesAndTypes(): void
    {
        $driver = $this->createMock(WebDriver::class);
        $driver->expects($this->once())->method('execute')->willReturnCallback(
            function (CustomCommand $command): array {
                $this->assertJsonStringEqualsJsonString(
                    '{"cmd":"Page.printToPDF","params":{"landscape":true,"paperWidth":8.5}}',
                    json_encode($command->getParameters(), JSON_THROW_ON_ERROR)
                );

                return ['data' => 'JVBERg=='];
            }
        );

        $result = (new ChromeDevTools($driver))->execute(Command::printToPdf([
            'landscape' => true,
            'paperWidth' => 8.5,
        ]));

        $this->assertSame(['data' => 'JVBERg=='], $result);
    }
}
