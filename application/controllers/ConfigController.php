<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Pdfexport\Forms\BackendConfigForm;
use ipl\Html\HtmlString;
use ipl\Web\Compat\CompatController;
use Icinga\Web\Widget\Tabs;

class ConfigController extends CompatController
{
    public function init()
    {
        $this->assertPermission('config/modules');

        parent::init();
    }

    public function backendAction()
    {
        $form = new BackendConfigForm();
        $form->setConfig(Config::module('pdfexport'));

        $form->handleRequest($this->getServerRequest());

        $this->mergeTabs($this->Module()->getConfigTabs()->activate('backend'));
        $this->addContent(HtmlString::create($form->render()));
    }

    protected function mergeTabs(Tabs $tabs): void
    {
        foreach ($tabs->getTabs() as $tab) {
            $this->tabs->add($tab->getName(), $tab);
        }
    }
}
