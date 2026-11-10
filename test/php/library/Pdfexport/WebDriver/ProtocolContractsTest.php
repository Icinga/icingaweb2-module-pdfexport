<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport\WebDriver;

use Icinga\Module\Pdfexport\WebDriver\Capabilities;
use Icinga\Module\Pdfexport\WebDriver\Command;
use Icinga\Module\Pdfexport\WebDriver\CommandName;
use Icinga\Module\Pdfexport\WebDriver\ElementPresentCondition;
use PHPUnit\Framework\TestCase;

class ProtocolContractsTest extends TestCase
{
    public function testFactoriesConstructSubclasses(): void
    {
        require_once __DIR__ . "/ProtocolDevToolsCommand.php";

        $command = new class (CommandName::GetPageSource) extends Command {
        };
        $capabilities = new class () extends Capabilities {
        };
        $condition = new class ('css selector', 'body') extends ElementPresentCondition {
            public function __construct(string $mechanism, string $value)
            {
                parent::__construct($mechanism, $value);
            }
        };
        $this->assertInstanceOf($command::class, $command::executeScript('return 1;'));
        $this->assertInstanceOf($command::class, $command::getPageSource());
        $this->assertInstanceOf($command::class, $command::findElement('css selector', 'body'));
        $this->assertInstanceOf($command::class, $command::printPage([]));
        $this->assertInstanceOf(ProtocolDevToolsCommand::class, ProtocolDevToolsCommand::enableConsole());
        $this->assertInstanceOf(ProtocolDevToolsCommand::class, ProtocolDevToolsCommand::printToPdf([]));
        $this->assertInstanceOf($capabilities::class, $capabilities::chrome());
        $this->assertInstanceOf($capabilities::class, $capabilities::firefox());
        $this->assertInstanceOf($condition::class, $condition::byCssSelector('body'));
        $this->assertInstanceOf($condition::class, $condition::byLinkText('Home'));
        $this->assertInstanceOf($condition::class, $condition::byPartialLinkText('Hom'));
        $this->assertInstanceOf($condition::class, $condition::byId('main'));
        $this->assertInstanceOf($condition::class, $condition::byClassName('main'));
        $this->assertInstanceOf($condition::class, $condition::byName('main'));
        $this->assertInstanceOf($condition::class, $condition::byTagName('body'));
        $this->assertInstanceOf($condition::class, $condition::byXPath('//body'));
    }

    public function testScriptArgumentsPreserveNestedJsonValues(): void
    {
        $arguments = [null, true, 12, 1.5, 'text', ['names' => ['first', 'second'], 'enabled' => false]];
        $command = Command::executeScript('return arguments;', $arguments);

        $this->assertSame(CommandName::ExecuteScript, $command->getName());
        $this->assertSame(['script' => 'return arguments;', 'args' => $arguments], $command->getParameters());
        $this->assertSame(
            '{"script":"return arguments;","args":[null,true,12,1.5,"text",'
                . '{"names":["first","second"],"enabled":false}]}',
            json_encode($command->getParameters(), JSON_THROW_ON_ERROR)
        );
    }

    public function testCapabilitiesPreserveVendorOptionsAndConvertLegacyNames(): void
    {
        $capabilities = new Capabilities([
            'browserName'        => 'firefox',
            'platform'           => 'LINUX',
            'version'            => '123',
            'acceptSslCerts'     => true,
            'moz:firefoxOptions' => ['prefs' => ['devtools.jsonview.enabled' => false]],
            'firefox_profile'    => 'encoded-profile',
            'vendor:options'     => ['arguments' => ['--headless'], 'timeout' => 10],
            'unsupported'        => 'ignored',
        ]);

        $this->assertSame([
            'browserName'         => 'firefox',
            'platformName'        => 'linux',
            'browserVersion'      => '123',
            'acceptInsecureCerts' => true,
            'moz:firefoxOptions'  => [
                'prefs'   => ['devtools.jsonview.enabled' => false],
                'profile' => 'encoded-profile',
            ],
            'vendor:options' => ['arguments' => ['--headless'], 'timeout' => 10],
        ], $capabilities->toW3cCompatibleArray());
        $this->assertArrayHasKey('unsupported', $capabilities->toArray());
        $this->assertArrayNotHasKey('platformName', Capabilities::chrome()->toW3cCompatibleArray());
    }

    public function testExplicitFirefoxProfileTakesPrecedence(): void
    {
        $this->assertSame([
            'moz:firefoxOptions' => ['profile' => 'explicit-profile'],
        ], (new Capabilities([
            'moz:firefoxOptions' => ['profile' => 'explicit-profile'],
            'firefox_profile'    => 'legacy-profile',
        ]))->toW3cCompatibleArray());
    }
}
