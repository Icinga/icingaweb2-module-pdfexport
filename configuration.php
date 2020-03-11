<?php
// Icinga PDF Export | (c) 2019 Icinga GmbH | GPLv2

/** @var \Icinga\Application\Modules\Module $this */

$this->provideConfigTab('chrome', array(
    'title' => $this->translate('Configure the Chrome/Chromium connection'),
    'label' => $this->translate('Chrome'),
    'url'   => 'config/chrome'
));
