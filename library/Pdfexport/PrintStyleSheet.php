<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport;

use Icinga\Application\Icinga;
use Icinga\Web\StyleSheet;

/**
 * Icinga Web styles compiled with the PDF print theme
 */
class PrintStyleSheet extends StyleSheet
{
    protected function collect(): void
    {
        parent::collect();
        $this->lessCompiler->setTheme(implode(DIRECTORY_SEPARATOR, [
            Icinga::app()->getModuleManager()->getModule('pdfexport')->getCssDir(),
            'print.less',
        ]));

        $this->lessCompiler->setThemeMode($this->pubPath . '/css/modes/none.less');
    }
}
