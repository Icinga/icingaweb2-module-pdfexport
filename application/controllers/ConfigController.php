<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Controllers;

use Icinga\Module\Pdfexport\BackendLocator;
use Icinga\Module\Pdfexport\Forms\BackendConfigForm;
use Icinga\Module\Pdfexport\Forms\DeleteBackendForm;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use Icinga\Web\Widget\Tabs;
use ipl\Html\Attributes;
use ipl\Html\Contract\Form;
use ipl\Html\Table;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;

/**
 * Print backend configuration UI
 */
class ConfigController extends CompatController
{
    public function init()
    {
        $this->assertPermission('config/modules');

        parent::init();
    }

    /**
     * List configured print backends by priority
     *
     * @return void
     */
    public function backendsAction(): void
    {
        $button = new ButtonLink(
            $this->translate('Create a New Backend'),
            'pdfexport/config/createbackend',
            'plus',
            ['title' => $this->translate('Create a New Backend')],
        );
        $button->setBaseTarget('_next');
        $this->addContent($button);

        $table = new Table();
        $table->setAttributes(Attributes::create([
            'class' => 'table-row-selectable common-table',
            'data-base-target' => '_next',
        ]));
        $table->add(Table::tr([
            Table::th($this->translate('Backend')),
            Table::th($this->translate('Priority')),
        ]));

        $config = BackendLocator::getBackendConfig();

        $sections = [];
        foreach ($config as $name => $data) {
            $sections[] = [$name, $data, (int) $data->get('priority')];
        }

        usort($sections, function ($a, $b) {
            return $a[2] <=> $b[2];
        });

        foreach ($sections as [$name, $data]) {
            $table->add(Table::tr([
                Table::td([
                    new Icon('print'),
                    new Link($name, Url::fromPath('pdfexport/config/backend', ['backend' => $name])),
                ]),
                Table::td($data->get('priority')),
            ], [
                'class' => 'clickable',
            ]));
        }

        $this->mergeTabs($this->Module()->getConfigTabs()->activate('backends'));
        $this->addContent($table);
    }

    /**
     * Edit or rename an existing print backend
     *
     * @return void
     */
    public function backendAction(): void
    {
        $name = $this->params->shiftRequired('backend');
        $this->addTitleTab(sprintf($this->translate('Edit %s'), $name));

        $form = (new BackendConfigForm(BackendLocator::getBackendConfig(), $name))
            ->setCsrfCounterMeasureId(Session::getSession()->getId())
            ->on(Form::ON_SUBMIT, function (BackendConfigForm $form) use ($name) {
                Notification::success($form->getValue('name') === $name
                    ? $this->translate('Updated print backend')
                    : $this->translate('Print backend renamed'));
                $this->redirectNow('__CLOSE__');
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent((new ButtonLink(
            $this->translate('Delete'),
            Url::fromPath('pdfexport/config/deletebackend', ['backend' => $name]),
            'trash',
            ['class' => 'btn-remove']
        ))->openInModal());
        $this->addContent($form);
    }

    /**
     * Create a print backend
     *
     * @return void
     */
    public function createbackendAction(): void
    {
        $this->addTitleTab($this->translate('Create Print Backend'));

        $form = (new BackendConfigForm(BackendLocator::getBackendConfig()))
            ->setCsrfCounterMeasureId(Session::getSession()->getId())
            ->on(Form::ON_SUBMIT, function () {
                Notification::success($this->translate('Created new print backend'));
                $this->redirectNow('__CLOSE__');
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }

    /**
     * Confirm and delete a print backend without validating its settings
     *
     * @return void
     */
    public function deletebackendAction(): void
    {
        $name = $this->params->shiftRequired('backend');
        $this->addTitleTab(sprintf($this->translate('Delete %s'), $name));

        $form = (new DeleteBackendForm(BackendLocator::getBackendConfig(), $name))
            ->setAction(Url::fromPath('pdfexport/config/deletebackend', ['backend' => $name])->getAbsoluteUrl())
            ->setCsrfCounterMeasureId(Session::getSession()->getId())
            ->on(Form::ON_SUBMIT, function () {
                Notification::success($this->translate('Print backend deleted'));
                $this->redirectNow('__CLOSE__');
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }

    /**
     * Add module configuration tabs to the controller
     *
     * @param Tabs $tabs Module configuration tabs
     *
     * @return void
     */
    protected function mergeTabs(Tabs $tabs): void
    {
        foreach ($tabs->getTabs() as $tab) {
            $this->tabs->add($tab->getName(), $tab);
        }
    }
}
