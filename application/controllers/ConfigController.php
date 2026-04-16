<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Pdfexport\Forms\BackendConfigForm;
use Icinga\Web\Form\ConfigSectionForm;
use Icinga\Web\Notification;
use ipl\Html\Attributes;
use ipl\Html\Form;
use ipl\Html\HtmlString;
use ipl\Html\Table;
use ipl\Web\Compat\CompatController;
use Icinga\Web\Widget\Tabs;
use ipl\Web\Widget\ButtonLink;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;

class ConfigController extends CompatController
{
    public function init()
    {
        $this->assertPermission('config/modules');

        parent::init();
    }

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

        $config = Config::module('pdfexport');

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
                    new Link($name, 'pdfexport/config/backend?backend=' . $name),
                ]),
                Table::td($data->get('priority')),
            ], [
                'class' => 'clickable',
            ]));
        }

        $this->mergeTabs($this->Module()->getConfigTabs()->activate('backends'));
        $this->addContent($table);
    }

    public function backendAction(): void
    {
        $name = $this->params->shiftRequired('backend');
        $this->addTitleTab($this->translate(sprintf('Edit %s', $name)));

        $form = new BackendConfigForm();
        $form->setConfig(Config::module('pdfexport'));
        $form->setSection($name);

        $form->on(Form::ON_SUBMIT, function () use ($form) {
            Notification::success($this->translate('Updated print backend'));
            $this->redirectNow('__CLOSE__');
        });

        $form->on(ConfigSectionForm::ON_DELETE, function () use ($form) {
            Notification::success($this->translate('Print backend deleted'));
            $this->redirectNow('__CLOSE__');
        });

        $form->handleRequest($this->getServerRequest());

        $this->addContent(HtmlString::create($form->render()));
    }

    public function createbackendAction(): void
    {
        $this->addTitleTab($this->translate(sprintf('Create Print Backend')));

        $form = new BackendConfigForm();
        $form->setConfig(Config::module('pdfexport'));
        $form->setIsCreateForm(true);

        $form->on(Form::ON_SUBMIT, function () {
            Notification::success($this->translate('Created new print backend'));
            $this->redirectNow('__CLOSE__');
        });

        $form->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }

    protected function mergeTabs(Tabs $tabs): void
    {
        foreach ($tabs->getTabs() as $tab) {
            $this->tabs->add($tab->getName(), $tab);
        }
    }
}
