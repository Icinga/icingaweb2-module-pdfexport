<?php

namespace Icinga\Module\Pdfexport\WebDriver;

enum CommandName: string
{
    case NewSession = 'newSession';
    case Status = 'status';
    case Close = 'close';
    case Quit = 'quit';
    case ExecuteScript = 'executeScript';
    case GetPageSource = 'getPageSource';
    case PrintPage = 'printPage';
    case FindElement = 'findElement';

    public function getPath(): string
    {
        return match ($this) {
            self::NewSession => '/session',
            self::Status => '/status',
            self::Close => '/session/:sessionId/window',
            self::Quit => '/session/:sessionId',
            self::ExecuteScript => '/session/:sessionId/execute/sync',
            self::GetPageSource => '/session/:sessionId/source',
            self::PrintPage => '/session/:sessionId/print',
            self::FindElement => '/session/:sessionId/element',
        };
    }

    public function getMethod(): string
    {
        return match ($this) {
            self::NewSession => 'POST',
            self::Status => 'GET',
            self::Close => 'DELETE',
            self::Quit => 'DELETE',
            self::ExecuteScript => 'POST',
            self::GetPageSource => 'GET',
            self::PrintPage => 'POST',
            self::FindElement => 'POST',
        };
    }
}
