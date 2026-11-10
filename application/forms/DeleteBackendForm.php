<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Forms;

use Icinga\Application\Config;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Pdfexport\BackendLocator;
use ipl\Html\Html;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;

/**
 * Print backend deletion confirmation
 *
 * Deletion validates the request without validating backend connectivity or settings.
 */
class DeleteBackendForm extends CompatForm
{
    use CsrfCounterMeasure;

    /**
     * Create a new DeleteBackendForm
     *
     * @param Config $config Configuration with its persistence path set
     * @param string $name Existing section to delete
     *
     * @throws NotFoundError When the backend does not exist
     */
    public function __construct(protected readonly Config $config, protected readonly string $name)
    {
        if ($name === BackendLocator::GLOBAL_SECTION || ! $config->hasSection($name)) {
            throw new NotFoundError($this->translate('Print backend "%s" does not exist'), $name);
        }
    }

    protected function assemble(): void
    {
        $this->addCsrfCounterMeasure();
        $this->addHtml(Html::tag('p', sprintf(
            $this->translate('Do you really want to delete the print backend "%s"?'),
            $this->name
        )));
        $this->addElement('submit', 'delete', [
            'label' => $this->translate('Delete'),
            'class' => 'btn-remove',
        ]);
    }

    /**
     * Delete the backend before emitting the successful-submit event
     *
     * Save errors are handled by the form and prevent the successful-submit event.
     *
     * @return void
     *
     * @throws NotFoundError When the section no longer exists
     */
    protected function onSuccess(): void
    {
        if (! $this->config->hasSection($this->name)) {
            throw new NotFoundError($this->translate('Print backend "%s" does not exist'), $this->name);
        }

        $this->config->removeSection($this->name);
        $this->config->setSection(BackendLocator::GLOBAL_SECTION, [BackendLocator::CONFIGURED_SETTING => '1']);
        $this->config->saveIni();
    }
}
