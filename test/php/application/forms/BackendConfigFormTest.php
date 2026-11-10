<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport\Forms;

use Error;
use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Application\Config;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Pdfexport\BackendLocator;
use Icinga\Module\Pdfexport\Forms\BackendConfigForm;
use Icinga\Module\Pdfexport\Forms\DeleteBackendForm;
use ipl\Html\Contract\Form;
use ipl\Web\Compat\CompatForm;
use RuntimeException;
use Tests\Icinga\Module\Pdfexport\Lib\ConfigTestCase;

class BackendConfigFormTest extends ConfigTestCase
{
    /** @var array<string, mixed> */
    protected array $originalServer;

    /** @var list<string> */
    protected array $temporaryFiles = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->originalServer = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
    }

    public function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        foreach ($this->temporaryFiles as $path) {
            unlink($path);
        }

        parent::tearDown();
    }

    public function testCreatePersistsOnlySettingsAndKeepsOptionalDefaults(): void
    {
        $config = $this->createConfig([]);
        $form = new BackendConfigForm($config);
        $submitted = false;
        $form->on(Form::ON_SUBMIT, function () use ($config, &$submitted): void {
            $saved = Config::fromIni($config->getConfigFile());
            $this->assertSame('local_chrome', $saved->get('local', 'type'));
            $submitted = true;
        });

        $this->submit($form, $this->localValues('local'));

        $this->assertTrue($submitted);
        $saved = Config::fromIni($config->getConfigFile());
        $this->assertSame([
            'priority' => '100',
            'enabled' => 'y',
            'type' => 'local_chrome',
            'force_temp_storage' => '0',
        ], $saved->getSection('local')->toArray());
        $this->assertSame('1', $saved->get('_global', 'configured'));
        $this->assertSame('/usr/bin/google-chrome', $saved->get('local', 'binary', '/usr/bin/google-chrome'));
    }

    public function testInvalidOrDuplicateNamesNeverMutateConfiguration(): void
    {
        foreach (['existing', '_global', '', '[bad]', ' leading', "bad\nname"] as $name) {
            $config = $this->createConfig(['existing' => ['type' => 'local_chrome', 'binary' => '/keep/chrome']]);
            $before = $config->toArray();
            $form = new BackendConfigForm($config);

            $this->submit($form, $this->localValues($name));

            $this->assertFalse($form->isValid(), $name);
            $this->assertSame($before, $config->toArray(), $name);
            $this->assertSame('', file_get_contents($config->getConfigFile()), $name);
        }
    }

    public function testEditPopulatesSavedTypeAndFieldsBeforeAssembly(): void
    {
        $config = $this->createConfig([
            'remote' => ['type' => 'remote_chrome', 'host' => 'http://chrome.internal', 'port' => '9333'],
        ]);
        $form = (new BackendConfigForm($config, 'remote'))->setCsrfCounterMeasureId('test-backend');
        $form->ensureAssembled();

        $this->assertSame('remote', $form->getValue('name'));
        $this->assertSame('remote_chrome', $form->getValue('type'));
        $this->assertSame('http://chrome.internal', $form->getValue('host'));
        $this->assertSame('9333', $form->getValue('port'));
        $this->assertSame('text', $form->getElement('host')->getType());
        $this->assertSame('hidden', $form->getElement('binary')->getType());
    }

    public function testRenameUsesSubmittedTypeAndPreservesOtherSections(): void
    {
        $config = $this->createConfig([
            'old' => ['type' => 'remote_chrome', 'host' => 'http://unreachable.invalid', 'priority' => '50'],
            'other' => ['type' => 'local_chrome', 'enabled' => 'n', 'binary' => '/keep/chrome'],
        ]);
        $form = new BackendConfigForm($config, 'old');

        $this->submit($form, $this->localValues('Renamed / Chrome & reports'));

        $this->assertTrue($form->isValid());
        $saved = Config::fromIni($config->getConfigFile());
        $this->assertFalse($saved->hasSection('old'));
        $this->assertSame('local_chrome', $saved->get('Renamed / Chrome & reports', 'type'));
        $this->assertSame('/keep/chrome', $saved->get('other', 'binary'));
        $this->assertSame('1', $saved->get('_global', 'configured'));
    }

    public function testUnchangedNameCanBeSaved(): void
    {
        $config = $this->createConfig(['local' => ['type' => 'local_chrome', 'priority' => '50']]);
        $form = new BackendConfigForm($config, 'local');

        $this->submit($form, $this->localValues('local'));

        $this->assertTrue($form->isValid());
        $this->assertSame('100', Config::fromIni($config->getConfigFile())->get('local', 'priority'));
    }

    public function testTypeAutosubmissionDoesNotSave(): void
    {
        $config = $this->createConfig([]);
        $form = new BackendConfigForm($config);
        $values = $this->localValues('local');
        unset($values['submit']);

        $this->submit($form, $values);

        $this->assertFalse($form->hasBeenSubmitted());
        $this->assertTrue($config->isEmpty());
        $this->assertSame('', file_get_contents($config->getConfigFile()));
    }

    public function testSaveFailureSurfacesAnErrorWithoutSuccessfulSubmit(): void
    {
        $config = $this->getMockBuilder(Config::class)->onlyMethods(['saveIni'])->getMock();
        $config->expects($this->once())->method('saveIni')->willThrowException(new RuntimeException('Disk is full'));
        $form = new BackendConfigForm($config);
        $submitted = false;
        $form->on(Form::ON_SUBMIT, function () use (&$submitted): void {
            $submitted = true;
        });

        $this->submit($form, $this->localValues('local'));

        $this->assertFalse($submitted);
        $this->assertContains('Disk is full', $form->getMessages());
    }

    public function testMissingOriginalCannotBeEdited(): void
    {
        $this->expectException(NotFoundError::class);

        new BackendConfigForm($this->createConfig([]), 'missing');
    }

    public function testOriginalRemovedBeforeSubmissionIsNotRecreated(): void
    {
        $config = $this->createConfig(['original' => ['type' => 'local_chrome']]);
        $form = new BackendConfigForm($config, 'original');
        $config->removeSection('original');

        $this->submit($form, $this->localValues('renamed'));

        $this->assertTrue($config->isEmpty());
        $this->assertSame('', file_get_contents($config->getConfigFile()));
        $this->assertNotEmpty($form->getMessages());
    }

    public function testInvalidBackendCanBeDeletedAndDoesNotRestoreDefault(): void
    {
        $config = $this->replaceConfig([
            'broken' => ['type' => 'not-a-backend', 'priority' => '-1', 'host' => 'http://unreachable.invalid'],
        ]);
        $config->setConfigFile($this->temporaryConfigFile());
        $form = new DeleteBackendForm($config, 'broken');

        $this->submit($form, ['delete' => 'Delete']);

        $this->assertTrue($form->isValid());
        $saved = Config::fromIni($config->getConfigFile());
        $this->assertSame(['_global' => ['configured' => '1']], $saved->toArray());
        $this->assertTrue(BackendLocator::getBackendConfig()->isEmpty());
        $this->assertNull((new BackendLocator())->getFirstSupportedBackend());
    }

    public function testDeletionRejectsInvalidCsrfTokenBeforeMutation(): void
    {
        unset($_SERVER['HTTP_SEC_FETCH_SITE']);
        $config = $this->createConfig(['broken' => ['type' => 'not-a-backend']]);
        $before = $config->toArray();
        $form = new DeleteBackendForm($config, 'broken');

        try {
            $this->submit($form, ['delete' => 'Delete', 'CSRFToken' => 'forged-token']);
            $this->fail('Invalid CSRF tokens must be rejected');
        } catch (Error $error) {
            $this->assertSame('Invalid CSRF token provided', $error->getMessage());
        }

        $this->assertSame($before, $config->toArray());
        $this->assertSame('', file_get_contents($config->getConfigFile()));
    }

    public function testMetadataCannotBeEditedOrDeletedAsBackend(): void
    {
        $config = $this->createConfig(['_global' => ['configured' => '1']]);

        try {
            new BackendConfigForm($config, '_global');
            $this->fail('Metadata cannot be edited as a backend');
        } catch (NotFoundError) {
        }

        $this->expectException(NotFoundError::class);

        new DeleteBackendForm($config, '_global');
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     *
     * @return Config
     */
    protected function createConfig(array $sections): Config
    {
        return Config::fromArray($sections)->setConfigFile($this->temporaryConfigFile());
    }

    protected function temporaryConfigFile(): string
    {
        $path = tempnam('/tmp', 'pdfexport-test-');
        $this->assertIsString($path);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @param CompatForm $form
     * @param array<string, mixed> $values
     *
     * @return void
     */
    protected function submit(CompatForm $form, array $values): void
    {
        $form->setCsrfCounterMeasureId('test-backend');
        $form->handleRequest((new ServerRequest('POST', '/pdfexport/config/create'))->withParsedBody($values));
    }

    /**
     * @param string $name
     *
     * @return array<string, string>
     */
    protected function localValues(string $name): array
    {
        return [
            'name' => $name,
            'priority' => '100',
            'enabled' => 'y',
            'type' => 'local_chrome',
            'binary' => '',
            'host' => '',
            'port' => '',
            'force_temp_storage' => '0',
            'submit' => 'Save Changes',
        ];
    }
}
