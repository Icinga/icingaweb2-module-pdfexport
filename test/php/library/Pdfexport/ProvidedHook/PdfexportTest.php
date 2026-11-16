<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport\ProvidedHook;

use Icinga\Application\Hook;
use Icinga\Application\Hook\PdfexportHook;
use Icinga\Module\Pdfexport\Backend\PfdPrintBackend;
use Icinga\Module\Pdfexport\BackendLocator;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\ProvidedHook\Pdfexport;
use Icinga\Test\BaseTestCase;
use ipl\Html\Html;
use ReflectionProperty;
use RuntimeException;

class PdfexportTest extends BaseTestCase
{
    public function testFirstCanReturnAnotherRegisteredExporterImplementation(): void
    {
        $alternative = new class () extends PdfexportHook {
            public function isSupported(): bool
            {
                return true;
            }

            public function streamPdfFromHtml($html, $filename): never
            {
                throw new RuntimeException('Streaming is not part of this support check');
            }

            public function htmlToPdf($html): string
            {
                return '%PDF-alternative';
            }
        };
        $hooks = new ReflectionProperty(Hook::class, 'hooks');
        $instances = new ReflectionProperty(Hook::class, 'instances');
        $originalHooks = $hooks->getValue();
        $originalInstances = $instances->getValue();

        try {
            $hooks->setValue(null, []);
            $instances->setValue(null, []);
            // The nondeprecated Module::provideHook() is protected and delegates to this registry API.
            // @phpstan-ignore staticMethod.deprecated
            Hook::register('Pdfexport', 'alternative', $alternative::class, true);

            // Legacy callers rely on this compatibility entry point selecting every registered implementation.
            // @phpstan-ignore staticMethod.deprecated
            $this->assertInstanceOf($alternative::class, Pdfexport::first());
        } finally {
            $hooks->setValue(null, $originalHooks);
            $instances->setValue(null, $originalInstances);
        }
    }

    public function testLegacyRenderedStringReachesBackendWithoutEscapingMarkup(): void
    {
        $html = '<section><h1>Überblick 日本語 😀</h1><p>5 &lt; 10</p></section>';
        $backend = $this->createMock(PfdPrintBackend::class);
        $backend->expects($this->once())->method('toPdf')->willReturnCallback(
            function (PrintableHtmlDocument $document) use ($html): string {
                $this->assertSame($html, $document->getContent()[0]->render());

                return '%PDF-legacy';
            }
        );
        $backend->expects($this->once())->method('close');

        $this->assertSame('%PDF-legacy', $this->createExporter($backend)->htmlToPdf($html));
    }

    public function testPrintableDocumentRetainsItsIdentityAndOptions(): void
    {
        $document = (new PrintableHtmlDocument())
            ->setContent(Html::tag('p', 'Current export'))
            ->setTitle('Current title')
            ->removeMargins();
        $backend = $this->createMock(PfdPrintBackend::class);
        $backend->expects($this->once())->method('toPdf')->with($this->identicalTo($document))
            ->willReturn('%PDF-current');
        $backend->expects($this->once())->method('supportsCoverPage')->willReturn(false);
        $backend->expects($this->once())->method('close');

        $this->assertSame('%PDF-current', $this->createExporter($backend)->htmlToPdf($document));
    }

    public function testUnavailableBackendRaisesAnExplicitExportFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^No supported PDF backend available\.$/');

        $this->createExporter(null)->htmlToPdf('<p>Legacy export</p>');
    }

    protected function createExporter(?PfdPrintBackend $backend): Pdfexport
    {
        $locator = $this->createStub(BackendLocator::class);
        $locator->method('getFirstSupportedBackend')->willReturn($backend);

        return new class ($locator) extends Pdfexport {
            public function __construct(BackendLocator $locator)
            {
                $this->locator = $locator;
            }
        };
    }
}
