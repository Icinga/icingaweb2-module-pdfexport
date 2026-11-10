<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport;

use Icinga\Module\Pdfexport\Backend\PfdPrintBackend;
use Icinga\Module\Pdfexport\Backend\WebdriverBackend;
use Icinga\Module\Pdfexport\BackendLocator;
use Icinga\Module\Pdfexport\WebDriver\CommandExecutor;
use Icinga\Module\Pdfexport\WebDriver\CommandInterface;
use Icinga\Module\Pdfexport\WebDriver\CommandName;
use Icinga\Module\Pdfexport\WebDriver\Response;
use Icinga\Module\Pdfexport\WebDriver\WebDriver;
use RuntimeException;
use Tests\Icinga\Module\Pdfexport\Lib\ConfigTestCase;

class BackendLocatorTest extends ConfigTestCase
{
    public function testLegacyLocalSettingsAreEnabledWithoutMutatingRegisteredConfig(): void
    {
        $original = $this->replaceConfig([
            'chrome' => ['binary' => '/opt/chromium', 'force_temp_storage' => '1'],
        ]);
        $before = $original->toArray();
        $snapshot = BackendLocator::getBackendConfig();

        $this->assertSame('local_chrome', $snapshot->get('chrome', 'type'));
        $this->assertSame('y', $snapshot->get('chrome', 'enabled'));
        $this->assertSame('100', $snapshot->get('chrome', 'priority'));
        $this->assertSame('/opt/chromium', $snapshot->get('chrome', 'binary'));
        $this->assertSame('1', $snapshot->get('chrome', 'force_temp_storage'));
        $this->assertSame($original->getConfigFile(), $snapshot->getConfigFile());

        $snapshot->getSection('chrome')->binary = '/other/chromium';

        $this->assertSame($before, $original->toArray());
    }

    public function testLegacyRemoteSettingsRetainHostAndPort(): void
    {
        $this->replaceConfig(['chrome' => ['host' => 'http://chrome.internal', 'port' => '9333']]);
        $snapshot = BackendLocator::getBackendConfig();

        $this->assertSame('remote_chrome', $snapshot->get('chrome', 'type'));
        $this->assertSame('http://chrome.internal', $snapshot->get('chrome', 'host'));
        $this->assertSame('9333', $snapshot->get('chrome', 'port'));

        $backend = $this->createStub(PfdPrintBackend::class);
        $backend->method('isSupported')->willReturn(true);
        $locator = $this->getMockBuilder(BackendLocator::class)
            ->onlyMethods(['connectToRemoteChrome'])->getMock();
        $locator->expects($this->once())->method('connectToRemoteChrome')->with('chrome')->willReturn($backend);

        $this->assertSame($backend, $locator->getFirstSupportedBackend());
    }

    public function testUnconfiguredInstallationRetainsImplicitLocalChrome(): void
    {
        $original = $this->replaceConfig([]);
        $backend = $this->createStub(PfdPrintBackend::class);
        $backend->method('isSupported')->willReturn(true);
        $locator = $this->getMockBuilder(BackendLocator::class)
            ->onlyMethods(['connectToLocalChrome'])->getMock();
        $locator->expects($this->once())->method('connectToLocalChrome')->with('chrome')->willReturn($backend);

        $this->assertSame($backend, $locator->getFirstSupportedBackend());
        $this->assertTrue($original->isEmpty());
    }

    public function testExplicitEmptyConfigurationDoesNotRestoreImplicitChrome(): void
    {
        $this->replaceConfig(['_global' => ['configured' => '1']]);
        $locator = $this->getMockBuilder(BackendLocator::class)
            ->onlyMethods(['getSingleBackend'])->getMock();
        $locator->expects($this->never())->method('getSingleBackend');

        $this->assertTrue(BackendLocator::getBackendConfig()->isEmpty());
        $this->assertNull($locator->getFirstSupportedBackend());
    }

    public function testDisabledBackendPreventsImplicitDefaultAndIsNotConnected(): void
    {
        $this->replaceConfig(['chrome' => ['type' => 'local_chrome', 'enabled' => 'n']]);
        $locator = $this->getMockBuilder(BackendLocator::class)
            ->onlyMethods(['getSingleBackend'])->getMock();
        $locator->expects($this->never())->method('getSingleBackend');

        $this->assertSame('n', BackendLocator::getBackendConfig()->get('chrome', 'enabled'));
        $this->assertNull($locator->getFirstSupportedBackend());
    }

    public function testUnsupportedWebDriverWithFailedCleanupDoesNotBlockFallback(): void
    {
        $this->replaceConfig([
            'disabled' => ['type' => 'chrome_webdriver', 'enabled' => 'n', 'priority' => '0'],
            'healthy' => ['type' => 'firefox_webdriver', 'enabled' => 'y', 'priority' => '20'],
            'broken' => ['type' => 'chrome_webdriver', 'enabled' => 'y', 'priority' => '10'],
        ]);
        $executor = $this->createMock(CommandExecutor::class);
        $requests = [];
        $executor->expects($this->exactly(2))->method('execute')->willReturnCallback(
            function (?string $sessionId, CommandInterface $command) use (&$requests): Response {
                $this->assertSame('broken-session', $sessionId);
                $requests[] = $command->getName();

                throw new RuntimeException('Browser connection failed');
            }
        );
        $healthy = $this->createStub(PfdPrintBackend::class);
        $healthy->method('isSupported')->willReturn(true);
        $locator = $this->getMockBuilder(BackendLocator::class)
            ->onlyMethods(['connectToWebDriver'])->getMock();
        $sections = [];
        $locator->expects($this->exactly(2))->method('connectToWebDriver')->willReturnCallback(
            function (string $section) use ($executor, $healthy, &$sections): PfdPrintBackend {
                $sections[] = $section;
                if ($section === 'healthy') {
                    return $healthy;
                }

                $driver = new class ($executor) extends WebDriver {
                    public function __construct(CommandExecutor $executor)
                    {
                        parent::__construct($executor, 'broken-session');
                    }
                };

                return new class ($driver) extends WebdriverBackend {
                    public function __construct(WebDriver $driver)
                    {
                        $this->driver = $driver;
                    }
                };
            }
        );

        $this->assertSame($healthy, $locator->getFirstSupportedBackend());
        $this->assertSame(['broken', 'healthy'], $sections);
        $this->assertSame([CommandName::PrintPage, CommandName::Quit], $requests);
    }
}
