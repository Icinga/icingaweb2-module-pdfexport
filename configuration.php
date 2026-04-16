<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/** @var \Icinga\Application\Modules\Module $this */

$this->provideConfigTab('backends', array(
    'title' => $this->translate('Configure Chrome/WebDriver connections'),
    'label' => $this->translate('Backends'),
    'url'   => 'config/backends'
));
