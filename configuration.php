<?php
// Icinga PDF Export | (c) 2019 Icinga GmbH | GPLv2

/** @var \Icinga\Application\Modules\Module $this */

$this->provideConfigTab('binary', array(
    'title' => $this->translate('Configure the Chrome/Chromium binary'),
    'label' => $this->translate('Binary'),
    'url'   => 'config/binary'
));
