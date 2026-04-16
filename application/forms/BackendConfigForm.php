<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Forms;

use Exception;
use Icinga\Module\Pdfexport\Backend\Chromedriver;
use Icinga\Module\Pdfexport\Backend\Geckodriver;
use Icinga\Module\Pdfexport\Backend\HeadlessChromeBackend;
use Icinga\Web\Form\ConfigSectionForm;
use ipl\Validator\CallbackValidator;

class BackendConfigForm extends ConfigSectionForm
{
    public function assemble(): void
    {
        $this->addSectionNameElement();

        $this->addElement('number', 'priority', [
            'label'       => $this->translate('Priority'),
            'required'    => true,
            'placeholder' => 100,
            'min'         => 0,
            'description' => $this->translate('The priority of the backend. A lower priority will be used first.'),
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
        ]);

        $type = $this->getPopulatedValue('type') ?? $this->getConfigValue('type');

        switch ($type) {
            case 'remote_chrome':
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
                                $validator->addMessage(t(
                                    'Chrome/Chromium supporting headless mode required'
                                    . ' which is provided since version %s. Version detected: %s',
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
                                $chrome = (HeadlessChromeBackend::createLocal($value));
                                $version = $chrome->getVersion();
                            } catch (Exception $e) {
                                $validator->addMessage($e->getMessage());
                                return false;
                            }

                            if ($version < HeadlessChromeBackend::MIN_SUPPORTED_CHROME_VERSION) {
                                $validator->addMessage(t(
                                    'Chrome/Chromium supporting headless mode required'
                                    . ' which is provided since version %s. Version detected: %s',
                                ));
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

        $this->addButtonElements();
    }
}
