<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Forms;

use Exception;
use Icinga\Module\Pdfexport\Backend\Chromedriver;
use Icinga\Module\Pdfexport\Backend\Geckodriver;
use Icinga\Module\Pdfexport\Backend\HeadlessChromeBackend;
use Icinga\Module\Pdfexport\Form\ConfigForm;
use Icinga\Module\Pdfexport\WebDriverType;
use ipl\Validator\CallbackValidator;

class BackendConfigForm extends ConfigForm
{
    public function assemble()
    {
        $this->addElement('text', 'chrome_binary', [
            'label'       => $this->translate('Local Binary'),
            'placeholder' => '/usr/bin/google-chrome',
            'validators' => [
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
                            . ' which is provided since version %s. Version detected: %s'
                        ));
                    }

                    return true;
                }),
            ],
        ]);

        $this->addElement('checkbox', 'chrome_force_temp_storage', [
            'label'     => $this->translate('Force local temp storage')
        ]);

        $this->addElement('text', 'chrome_host', [
            'label'         => $this->translate('Remote Host'),
            'validators'    => [
                new CallbackValidator(function ($value, CallbackValidator $validator) {
                if ($value === null) {
                    return true;
                }

                $port = $this->getValue('chrome_port') ?: 9222;

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
                        . ' which is provided since version %s. Version detected: %s'
                    ));
                    return false;
                }

                return true;
            })]
        ]);

        $this->addElement('number', 'chrome_port', [
            'label'         => $this->translate('Remote Port'),
            'placeholder'   => 9222,
            'min'           => 1,
            'max'           => 65535
        ]);

        $this->addElement('text', 'webdriver_host', [
            'label'         => $this->translate('WebDriver Host'),
            'validators'    => [new CallbackValidator(function ($value, CallbackValidator $validator) {
                if ($value === null) {
                    return true;
                }

                $port = $this->getValue('webdriver_port') ?: 4444;
                $type = $this->getValue('webdriver_type') ?: 'chrome';

                try {
                    $url = "$value:$port";
                    $backend = match (WebDriverType::from($type)) {
                        WebDriverType::Chrome => new Chromedriver($url),
                        WebDriverType::Firefox => new Geckodriver($url),
                        default => throw new Exception("Invalid webdriver type $type"),
                    };

                    if (! $backend->isSupported()) {
                        $validator->addMessage(t('The webdriver server reports that it is unable to generate PDFs'));
                        return false;
                    }

                } catch (Exception $e) {
                    $validator->addMessage($e->getMessage());
                    return false;
                }
                return true;
            })]
        ]);

        $this->addElement('number', 'webdriver_port', [
            'label'         => $this->translate('WebDriver Port'),
            'placeholder'   => 4444,
            'min'           => 1,
            'max'           => 65535,
        ]);

        $this->addElement('select', 'webdriver_type', [
            'label'         => $this->translate('WebDriver Type'),
            'multiOptions'  => array_merge(
                ['' => sprintf(' - %s - ', t('Please choose'))],
                [
                    'firefox' => t('Firefox'),
                    'chrome' => t('Chrome'),
                ],
            ),
        ]);

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Store')
        ]);
    }
}
