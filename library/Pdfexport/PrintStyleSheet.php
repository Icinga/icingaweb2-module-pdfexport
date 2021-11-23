<?php

/* Icinga PDF Export | (c) 2021 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Pdfexport;

use Icinga\Application\Icinga;
use Icinga\Web\StyleSheet;

class PrintStyleSheet extends StyleSheet
{
    protected function collect()
    {
        parent::collect();

        $this->lessCompiler->setTheme(join(DIRECTORY_SEPARATOR, [
            Icinga::app()->getModuleManager()->getModule('pdfexport')->getCssDir(),
            'print.less'
        ]));

        if (method_exists($this->lessCompiler, 'setThemeMode')) {
            $this->lessCompiler->setThemeMode($this->pubPath . '/css/modes/none.less');
        }
    }
}
