<?php
// Icinga PDF Export | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Pdfexport\Forms;

use Icinga\Forms\ConfigForm;
use Icinga\Module\Pdfexport\HeadlessChrome;

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
            'label'       => $this->translate('Binary'),
            'placeholder' => '/bin/google-chrome',
            'validators'  => [new \Zend_Validate_Callback(function ($value) {
                $chrome = (new HeadlessChrome())
                    ->setBinary($value);

                try {
                    $version = $chrome->getVersion();
                } catch (\Exception $e) {
                    $this->addErrorMessage($e->getMessage());

                    return false;
                }

                if ($version < 59) {
                    $this->addErrorMessage(sprintf(
                        'PDF exports require Chrome/Chromium supporting headless mode which is provided since'
                        . ' version 59. Version detected: %s',
                        $version
                    ));

                    return false;
                }

                return true;
            })]
        ]);
    }
}
