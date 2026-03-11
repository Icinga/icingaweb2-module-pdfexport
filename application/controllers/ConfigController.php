<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Pdfexport\Forms\ChromeBinaryForm;
use Icinga\Web\Controller;

class ConfigController extends Controller
{
    public function init()
    {
        $this->assertPermission('config/modules');

        parent::init();
    }

    public function chromeAction()
    {
        $form = (new ChromeBinaryForm())
            ->setIniConfig(Config::module('pdfexport'));

        $form->handleRequest();

        $this->view->tabs = $this->Module()->getConfigTabs()->activate('chrome');
        $this->view->form = $form;
    }
}
