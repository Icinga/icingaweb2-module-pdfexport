<?php
// Icinga PDF Export | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Pdfexport\Forms;

use Exception;
use Icinga\Forms\ConfigForm;
use Icinga\Module\Pdfexport\HeadlessChrome;
use Zend_Validate_Callback;

class ChromeBinaryForm extends ConfigForm
{
    public function init()
    {
        $this->setName('pdfexport_binary');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    public function createElements(array $formData)
    {
        $this->addElement('text', 'chrome_binary', [
            'label'       => $this->translate('Local Binary'),
            'placeholder' => '/bin/google-chrome',
            'validators'  => [new Zend_Validate_Callback(function ($value) {
                $chrome = (new HeadlessChrome())
                    ->setBinary($value);

                try {
                    $version = $chrome->getVersion();
                } catch (Exception $e) {
                    $this->getElement('chrome_binary')->addError($e->getMessage());
                    return true;
                }

                if ($version < 59) {
                    $this->getElement('chrome_binary')->addError(sprintf(
                        $this->translate(
                            'Chrome/Chromium supporting headless mode required'
                            . ' which is provided since version 59. Version detected: %s'
                        ),
                        $version
                    ));
                }

                return true;
            })]
        ]);

        $this->addElement('text', 'chrome_host', [
            'label'         => $this->translate('Remote Host'),
            'validators'    => [new Zend_Validate_Callback(function ($value) {
                if ($value === null) {
                    return true;
                }

                $port = $this->getValue('chrome_port') ?: 9222;

                $chrome = (new HeadlessChrome())
                    ->setRemote($value, $port);

                try {
                    $version = $chrome->getVersion();
                } catch (Exception $e) {
                    $this->getElement('chrome_host')->addError($e->getMessage());
                    return true;
                }

                if ($version < 59) {
                    $this->getElement('chrome_host')->addError(sprintf(
                        $this->translate(
                            'Chrome/Chromium supporting headless mode required'
                            . ' which is provided since version 59. Version detected: %s'
                        ),
                        $version
                    ));
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
    }
}
