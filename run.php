<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/** @var \Icinga\Application\Modules\Module $this */

// Module::includeScript() includes this file in Module scope, where protected methods are accessible.
$this->provideHook('Pdfexport'); // @phpstan-ignore method.protected

require_once __DIR__ . '/vendor/autoload.php';
