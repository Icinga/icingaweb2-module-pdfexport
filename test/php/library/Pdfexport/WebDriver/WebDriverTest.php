<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport\WebDriver;

use Exception;
use Icinga\Module\Pdfexport\WebDriver\Command;
use Icinga\Module\Pdfexport\WebDriver\CommandExecutor;
use Icinga\Module\Pdfexport\WebDriver\CommandName;
use Icinga\Module\Pdfexport\WebDriver\ConditionInterface;
use Icinga\Module\Pdfexport\WebDriver\Response;
use Icinga\Module\Pdfexport\WebDriver\WebDriver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WebDriverTest extends TestCase
{
    public function testWaitPollsUntilTheConditionSucceeds(): void
    {
        $executor = $this->createStub(CommandExecutor::class);
        $executor->method('execute')->willReturn(new Response('test-session'));
        $driver = $this->createDriver($executor);
        $condition = $this->createMock(ConditionInterface::class);
        $condition->expects($this->exactly(2))->method('apply')->with($driver)->willReturn(false, true);

        $this->assertTrue($driver->wait($condition, timeoutSeconds: 1, intervalMs: 0));
    }

    public function testWaitReturnsFalseWhenTheDeadlineHasPassed(): void
    {
        $executor = $this->createStub(CommandExecutor::class);
        $executor->method('execute')->willReturn(new Response('test-session'));
        $driver = $this->createDriver($executor);
        $condition = $this->createMock(ConditionInterface::class);
        $condition->expects($this->never())->method('apply');

        $this->assertFalse($driver->wait($condition, timeoutSeconds: 0));
    }

    public function testWaitRethrowsTheConditionFailureAfterTimeout(): void
    {
        $failure = new RuntimeException('Condition could not inspect the browser');
        $executor = $this->createStub(CommandExecutor::class);
        $executor->method('execute')->willReturn(new Response('test-session'));
        $driver = $this->createDriver($executor);
        $condition = $this->createMock(ConditionInterface::class);
        $condition->expects($this->once())->method('apply')->with($driver)->willThrowException($failure);

        try {
            $driver->wait($condition, timeoutSeconds: 1, intervalMs: 1000);
            $this->fail('A timed out wait must report the condition failure');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
    }

    public function testFailedQuitClosesSessionAndIsNeverRetried(): void
    {
        $failure = new RuntimeException('Remote session deletion failed');
        $executor = $this->createMock(CommandExecutor::class);
        $executor->expects($this->once())->method('execute')
            ->with('test-session', $this->callback(
                fn(Command $command): bool => $command->getName() === CommandName::Quit
            ))
            ->willThrowException($failure);
        $driver = $this->createDriver($executor);

        try {
            $driver->quit();
            $this->fail('Explicit quit must report the remote failure');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }

        $driver->quit();

        try {
            $driver->execute(Command::getPageSource());
            $this->fail('A failed quit must leave the session inactive');
        } catch (Exception $error) {
            $this->assertSame('Session is not active', $error->getMessage());
        }

        unset($driver);
    }

    public function testDestructorSuppressesRemoteDeletionFailure(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->expects($this->once())->method('execute')
            ->willThrowException(new RuntimeException('Remote server is unavailable'));
        $driver = $this->createDriver($executor);

        unset($driver);
    }

    public function testSuccessfulQuitIsIdempotent(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->expects($this->once())->method('execute')->willReturn(new Response('test-session'));
        $driver = $this->createDriver($executor);

        $driver->quit();
        $driver->quit();
        unset($driver);
    }

    protected function createDriver(CommandExecutor $executor): WebDriver
    {
        return new class ($executor, 'test-session') extends WebDriver {
            public function __construct(?CommandExecutor $executor, ?string $sessionId)
            {
                parent::__construct($executor, $sessionId);
            }
        };
    }
}
