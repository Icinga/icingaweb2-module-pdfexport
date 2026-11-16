<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport\Backend;

use Icinga\Module\Pdfexport\Backend\WebdriverBackend;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\WebDriver\Command;
use Icinga\Module\Pdfexport\WebDriver\CommandExecutor;
use Icinga\Module\Pdfexport\WebDriver\CommandName;
use Icinga\Module\Pdfexport\WebDriver\Response;
use Icinga\Module\Pdfexport\WebDriver\WebDriver;
use PHPUnit\Framework\TestCase;

class WebdriverBackendTest extends TestCase
{
    public function testUnicodeDocumentTravelsAsAScriptArgumentThroughThePrintWorkflow(): void
    {
        $html = '<html><head><title>Überblick</title></head><body>日本語 😀</body></html>';
        $document = $this->createStub(PrintableHtmlDocument::class);
        $document->method('render')->willReturn($html);
        $document->method('getPrintParametersForWebdriver')->willReturn([]);
        $executor = $this->createMock(CommandExecutor::class);
        $commands = [];
        $executor->expects($this->exactly(4))->method('execute')->willReturnCallback(
            function (?string $sessionId, Command $command) use ($html, &$commands): Response {
                $this->assertSame('unicode-session', $sessionId);
                $commands[] = $command->getName();

                switch ($command->getName()) {
                    case CommandName::ExecuteScript:
                        $parameters = json_decode(json_encode($command->getParameters(), JSON_THROW_ON_ERROR), true);
                        $this->assertSame([$html], $parameters['args']);
                        $this->assertStringContainsString('arguments[0]', $parameters['script']);
                        $this->assertStringNotContainsString('atob', $parameters['script']);

                        return new Response('unicode-session');
                    case CommandName::FindElement:
                        $this->assertSame(['using' => 'tag name', 'value' => 'body'], $command->getParameters());

                        return new Response('unicode-session', value: ['ELEMENT' => 'body']);
                    case CommandName::PrintPage:
                        $this->assertSame(['background' => true], $command->getParameters());

                        return new Response('unicode-session', value: base64_encode('%PDF-unicode'));
                    default:
                        return new Response('unicode-session');
                }
            }
        );
        $driver = new class ($executor, 'unicode-session') extends WebDriver {
            public function __construct(?CommandExecutor $executor, ?string $sessionId)
            {
                parent::__construct($executor, $sessionId);
            }
        };
        $backend = new class ($driver) extends WebdriverBackend {
            public function __construct(WebDriver $driver)
            {
                $this->driver = $driver;
            }
        };

        $this->assertSame('%PDF-unicode', $backend->toPdf($document));
        $backend->close();
        $this->assertSame([
            CommandName::ExecuteScript,
            CommandName::FindElement,
            CommandName::PrintPage,
            CommandName::Quit,
        ], $commands);
    }
}
