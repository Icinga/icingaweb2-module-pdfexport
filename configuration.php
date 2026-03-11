<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/** @var \Icinga\Application\Modules\Module $this */

$this->provideConfigTab('chrome', array(
    'title' => $this->translate('Configure the Chrome/Chromium connection'),
    'label' => $this->translate('Chrome'),
    'url'   => 'config/chrome'
));
