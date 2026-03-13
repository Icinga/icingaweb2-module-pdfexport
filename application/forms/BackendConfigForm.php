<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Forms;

use Exception;
use Icinga\Module\Pdfexport\Backend\Chromedriver;
use Icinga\Module\Pdfexport\Backend\Geckodriver;
use Icinga\Module\Pdfexport\Backend\HeadlessChromeBackend;
use Icinga\Module\Pdfexport\Form\ConfigForm;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Validator\CallbackValidator;

class BackendConfigForm extends ConfigForm
{
    public function assemble(): void
    {
        $this->add(HtmlElement::create(
            'div',
            ['class' => 'note'],
            t(
                'The precedence for the chosen backend is the same as in this configuration form. ' .
                'Backends that are not configured are skipped and backends further down the list act as a fallback.',
            ),
        ));

        $this->add(Html::tag('h2', t("WebDriver")));
        $this->add(Html::tag('p', t(
            'WebDriver is a API that allows software to automatically control and interact with a web browser, ' .
            'commonly used for automating website testing through tools like Selenium WebDriver.',
        )));

        $this->addElement('text', 'webdriver__host', [
            'label'       => $this->translate('Host'),
            'description' => $this->translate('Host address of the webdriver server'),
            'validators'  => [
                new CallbackValidator(function ($value, CallbackValidator $validator) {
                    if ($value === null) {
                        return true;
                    }

                    $port = $this->getValue('webdriver__port') ?: 4444;
                    $type = $this->getValue('webdriver__type') ?: 'chrome';

                    try {
                        $url = "$value:$port";
                        $backend = match ($type) {
                            'chrome' => new Chromedriver($url),
                            'firefox' => new Geckodriver($url),
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

        $this->addElement('number', 'webdriver__port', [
            'label'       => $this->translate('Port'),
            'description' => $this->translate('Port of the webdriver instance. (Default: 4444)'),
            'placeholder' => 4444,
            'min'         => 1,
            'max'         => 65535,
        ]);

        $this->addElement('select', 'webdriver__type', [
            'label'        => $this->translate('Type'),
            'description'  => $this->translate('The type of webdriver server.'),
            'multiOptions' => array_merge(
                ['' => sprintf(' - %s - ', t('Please choose'))],
                [
                    'firefox' => t('Firefox'),
                    'chrome'  => t('Chrome'),
                ],
            ),
        ]);

        $this->add(Html::tag('h2', t("Remote Chrome")));
        $this->add(Html::tag('p', t(
            'A remote chrome instance and it\'s debug interface can be used to create PDFs.',
        )));

        $this->addElement('text', 'remote_chrome_host', [
            'label'       => $this->translate('Host'),
            'description' => $this->translate('Host address of the server with the running web browser.'),
            'validators'  => [
                new CallbackValidator(function ($value, CallbackValidator $validator) {
                    if ($value === null) {
                        return true;
                    }

                    $port = $this->getValue('remote_chrome__port') ?: 9222;

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

        $this->addElement('number', 'remote_chrome__port', [
            'label'       => $this->translate('Port'),
            'description' => $this->translate('Port of the chrome developer tools. (Default: 9222)'),
            'placeholder' => 9222,
            'min'         => 1,
            'max'         => 65535,
        ]);

        $this->add(Html::tag('h2', t("Local Chrome")));
        $this->add(Html::tag('p', t(
            'Start a chrome instance on the same server as icingaweb2. This is always attempted as a fallback.',
        )));

        $this->addElement('text', 'local_chrome__binary', [
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

        $this->addElement('checkbox', 'local_chrome__force_temp_storage', [
            'label'       => $this->translate('Use temp storage'),
            'description' => $this->translate('Use temp storage to transfer the html to the local chrome instance.'),
        ]);

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Store'),
        ]);
    }
}
