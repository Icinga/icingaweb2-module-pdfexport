<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Forms;

use Exception;
use Icinga\Application\Config;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Pdfexport\Backend\Chromedriver;
use Icinga\Module\Pdfexport\Backend\Geckodriver;
use Icinga\Module\Pdfexport\Backend\HeadlessChromeBackend;
use Icinga\Module\Pdfexport\BackendLocator;
use ipl\Validator\CallbackValidator;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;

/**
 * Print backend configuration and persistence
 */
class BackendConfigForm extends CompatForm
{
    use CsrfCounterMeasure;

    /**
     * Create a new BackendConfigForm
     *
     * Existing settings are populated before request handling so submitted values
     * take precedence when the type-specific fields are assembled. Successful
     * submission saves the section before the submit event is emitted.
     *
     * @param Config $config Configuration with its persistence path set
     * @param ?string $originalName Existing section to edit, or null to create one
     *
     * @throws NotFoundError When the section to edit does not exist
     */
    public function __construct(protected readonly Config $config, protected readonly ?string $originalName = null)
    {
        if ($originalName !== null) {
            if ($originalName === BackendLocator::GLOBAL_SECTION || ! $config->hasSection($originalName)) {
                throw new NotFoundError($this->translate('Print backend "%s" does not exist'), $originalName);
            }

            $this->populate($config->getSection($originalName)->toArray());
            $this->populate(['name' => $originalName]);
        }
    }

    public function assemble(): void
    {
        $this->addCsrfCounterMeasure();

        $this->addElement('text', 'name', [
            'label' => $this->translate('Name'),
            'required' => true,
            'ignore' => true,
            'validators' => [
                new CallbackValidator(function ($value, CallbackValidator $validator): bool {
                    if (
                        ! is_string($value)
                        || $value !== trim($value)
                        || preg_match('/[\x00-\x1f\x7f\[\]]/', $value)
                    ) {
                        $validator->addMessage($this->translate(
                            'Backend names must not contain brackets, control characters, or surrounding whitespace'
                        ));

                        return false;
                    }

                    if ($value === BackendLocator::GLOBAL_SECTION) {
                        $validator->addMessage($this->translate('This name is reserved for module settings'));

                        return false;
                    }

                    if ($value !== $this->originalName && $this->config->hasSection($value)) {
                        $validator->addMessage($this->translate('A print backend with this name already exists'));

                        return false;
                    }

                    return true;
                }),
            ],
        ]);

        $this->addElement('number', 'priority', [
            'label'       => $this->translate('Priority'),
            'required'    => true,
            'placeholder' => 100,
            'min'         => 0,
            'description' => $this->translate('The priority of the backend. A lower priority will be used first.'),
        ]);

        $this->addElement('checkbox', 'enabled', [
            'label' => $this->translate('Enabled'),
            'value' => true,
            'description' => $this->translate('If the backend is not enabled, it will be ignored for PDF generation.'),
        ]);

        $this->addElement('select', 'type', [
            'label' => $this->translate('Type'),
            'multiOptions' => [
                '' => sprintf(' - %s - ', t('Please choose')),
                'chrome_webdriver' => t('Chrome WebDriver'),
                'firefox_webdriver' => t('Firefox WebDriver'),
                'remote_chrome' => t('Headless Chrome (Remote)'),
                'local_chrome' => t('Headless Chrome (Local)'),
            ],
            'required' => true,
            'class' => 'autosubmit',
            'value' => 'local_chrome',
        ]);

        $type = $this->getValue('type');

        switch ($type) {
            case 'remote_chrome':
                $this->addElement('hidden', 'binary');
                $this->addElement('hidden', 'force_temp_storage');

                $this->addElement('text', 'host', [
                    'label'       => $this->translate('Host'),
                    'description' => $this->translate('Host address of the server with the running web browser.'),
                    'required'    => true,
                    'validators'  => [
                        new CallbackValidator(function ($value, CallbackValidator $validator) {
                            $port = $this->getValue('port') ?: 9222;

                            try {
                                $chrome = HeadlessChromeBackend::createRemote($value, $port);
                                $version = $chrome->getVersion();
                            } catch (Exception $e) {
                                $validator->addMessage($e->getMessage());
                                return false;
                            }

                            if ($version < HeadlessChromeBackend::MIN_SUPPORTED_CHROME_VERSION) {
                                $validator->addMessage(sprintf(
                                    $this->translate(
                                        'Chrome/Chromium supporting headless mode required'
                                        . ' which is provided since version %s. Version detected: %s'
                                    ),
                                    HeadlessChromeBackend::MIN_SUPPORTED_CHROME_VERSION,
                                    $version
                                ));
                                return false;
                            }

                            return true;
                        }),
                    ],
                ]);

                $this->addElement('number', 'port', [
                    'label'       => $this->translate('Port'),
                    'description' => $this->translate('Port of the chrome developer tools. (Default: 9222)'),
                    'placeholder' => 9222,
                    'min'         => 1,
                    'max'         => 65535,
                ]);

                break;

            case 'local_chrome':
                $this->addElement('hidden', 'host');
                $this->addElement('hidden', 'port');

                $this->addElement('text', 'binary', [
                    'label'       => $this->translate('Binary'),
                    'placeholder' => '/usr/bin/google-chrome',
                    'description' => $this->translate('Path to the binary of the web browser.'),
                    'validators'  => [
                        new CallbackValidator(function ($value, CallbackValidator $validator) {
                            if (empty($value)) {
                                return true;
                            }

                            try {
                                $version = HeadlessChromeBackend::createLocal($value)->getVersion();
                            } catch (Exception $e) {
                                $validator->addMessage($e->getMessage());
                                return false;
                            }

                            if ($version < HeadlessChromeBackend::MIN_SUPPORTED_CHROME_VERSION) {
                                $validator->addMessage(sprintf(
                                    $this->translate(
                                        'Chrome/Chromium supporting headless mode required'
                                        . ' which is provided since version %s. Version detected: %s'
                                    ),
                                    HeadlessChromeBackend::MIN_SUPPORTED_CHROME_VERSION,
                                    $version
                                ));

                                return false;
                            }

                            return true;
                        }),
                    ],
                ]);

                $this->addElement('checkbox', 'force_temp_storage', [
                    'label'          => $this->translate('Use temp storage'),
                    'description'    => $this->translate(
                        'Use temp storage to transfer the html to the local chrome instance.'
                    ),
                    'checkedValue'   => '1',
                    'uncheckedValue' => '0',
                ]);

                break;

            case 'firefox_webdriver':
            case 'chrome_webdriver':
                $this->addElement('hidden', 'binary');
                $this->addElement('hidden', 'force_temp_storage');

                $this->addElement('text', 'host', [
                    'label'       => $this->translate('Host'),
                    'description' => $this->translate('Host address of the webdriver server'),
                    'required'    => true,
                    'validators'  => [
                        new CallbackValidator(function ($value, CallbackValidator $validator) use ($type) {
                            $port = $this->getValue('port') ?: 4444;

                            try {
                                $url = "$value:$port";
                                $backend = match ($type) {
                                    'chrome_webdriver' => new Chromedriver($url),
                                    'firefox_webdriver' => new Geckodriver($url),
                                    default => throw new Exception("Invalid webdriver type $type"),
                                };

                                if (! $backend->isSupported()) {
                                    $validator->addMessage(
                                        t('The webdriver server reports that it is unable to generate PDFs'),
                                    );
                                    return false;
                                }
                            } catch (Exception $e) {
                                $validator->addMessage($e->getMessage());
                                return false;
                            }
                            return true;
                        }),
                    ],
                ]);

                $this->addElement('number', 'port', [
                    'label'       => $this->translate('Port'),
                    'description' => $this->translate('Port of the webdriver instance. (Default: 4444)'),
                    'placeholder' => 4444,
                    'min'         => 1,
                    'max'         => 65535,
                ]);

                break;
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Save Changes'),
        ]);
    }

    /**
     * Save validated backend settings
     *
     * Empty optional values are omitted so runtime defaults remain active.
     * Save errors are handled by the form and prevent the successful-submit event.
     *
     * @return void
     *
     * @throws NotFoundError When the original section no longer exists
     */
    protected function onSuccess(): void
    {
        if ($this->originalName !== null && ! $this->config->hasSection($this->originalName)) {
            throw new NotFoundError($this->translate('Print backend "%s" does not exist'), $this->originalName);
        }

        $name = $this->getValue('name');
        $settings = array_intersect_key($this->getValues(), array_flip([
            'priority',
            'enabled',
            'type',
            'binary',
            'host',
            'port',
            'force_temp_storage',
        ]));
        $settings = array_filter($settings, static fn ($value): bool => $value !== null && $value !== '');

        $this->config->setSection($name, $settings);
        if ($this->originalName !== null && $this->originalName !== $name) {
            $this->config->removeSection($this->originalName);
        }

        $this->config->setSection(BackendLocator::GLOBAL_SECTION, [BackendLocator::CONFIGURED_SETTING => '1']);
        $this->config->saveIni();
    }
}
