<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/** @var \Icinga\Application\Modules\Module $this */

// Module::includeScript() includes this file in Module scope, where protected methods are accessible.
$this->provideConfigTab('backends', [ // @phpstan-ignore method.protected
    'title' => $this->translate('Configure Chrome/WebDriver connections'), // @phpstan-ignore method.protected
    'label' => $this->translate('Backends'), // @phpstan-ignore method.protected
    'url'   => 'config/backends',
]);
