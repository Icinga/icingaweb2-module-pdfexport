<?php

namespace Icinga\Module\Pdfexport\ChromeDevTools;

use Icinga\Module\Pdfexport\WebDriver\CustomCommand;
use Icinga\Module\Pdfexport\WebDriver\WebDriver;

class ChromeDevTools
{
    public function __construct(
        protected WebDriver $driver,
    ) {
    }

    public function execute(Command $command): mixed
    {
        $params = [
            'cmd' => $command->getName(),
            'params' => $command->getParameters(),
        ];

        $customCommand = new CustomCommand(
            'POST',
            '/session/:sessionId/goog/cdp/execute',
            $params,
        );

        return $this->driver->execute($customCommand);
    }
}
