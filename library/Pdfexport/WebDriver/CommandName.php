<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * Enum containing the implemented WebDriver commands.
 * @link https://www.w3.org/TR/webdriver/#commands
 */
enum CommandName: string
{
    /**
     * Create a new session.
     * @link https://www.w3.org/TR/webdriver/#dfn-new-sessions
     */
    case NewSession = 'newSession';

    /**
     * The the current status of the webdriver server.
     * @link https://www.w3.org/TR/webdriver/#dfn-status
     */
    case Status = 'status';

    /**
     * Close the window
     * @link https://www.w3.org/TR/webdriver/#dfn-close-window
     */
    case Close = 'close';

    /**
     * Close the webdriver session.
     * Implicitly closes the current window.
     * @link https://www.w3.org/TR/webdriver/#dfn-quit
     */
    case Quit = 'quit';

    /**
     * Execute JavaScript in the context of the currently selected frame or window.
     * @link https://www.w3.org/TR/webdriver/#dfn-execute-script
     */
    case ExecuteScript = 'executeScript';

    /**
     * Get the source of the current page.
     * @link https://www.w3.org/TR/webdriver/#dfn-get-page-source
     */
    case GetPageSource = 'getPageSource';

    /**
     * Print the current page.
     * @link https://www.w3.org/TR/webdriver/#dfn-print-page
     */
    case PrintPage = 'printPage';

    /**
     * Find an element on the page.
     * @link https://www.w3.org/TR/webdriver/#dfn-find-element
     */
    case FindElement = 'findElement';

    /**
     * Get the path to the endpoint of the command.
     * @return string
     */
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

    /**
     * Get the HTTP method of the command.
     * @return string
     */
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
