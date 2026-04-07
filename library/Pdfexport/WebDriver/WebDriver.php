<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

use Exception;

/**
 * Partial implementation of the WebDriver protocol.
 * @link https://www.w3.org/TR/webdriver/
 */
class WebDriver
{
    /**
     * Create a new WebDriver instance.
     * @param CommandExecutor|null $executor a command executor instance which is responsible for sending commands to
     * the webdriver server
     * @param string|null $sessionId the session if for the connection from the server from the `newSession` command
     */
    protected function __construct(
        protected ?CommandExecutor $executor,
        protected ?string $sessionId,
    ) {
    }

    public function __destruct()
    {
        $this->quit();
    }

    /**
     * Create a new WebDriver instance with a set of capabilities.
     *
     * @param string $url the host and port of the webdriver server
     * @param Capabilities $capabilities the capabilities to use for the session
     *
     * @return static
     * @throws Exception
     */
    public static function create(string $url, Capabilities $capabilities): static
    {
        $executor = new CommandExecutor($url);

        $params = [
            'capabilities' => [
                'firstMatch' => [(object) $capabilities->toW3cCompatibleArray()],
            ],
            'desiredCapabilities' => (object) $capabilities->toArray(),
        ];

        $cmd = new Command(CommandName::NewSession, $params);

        $response = $executor->execute(null, $cmd);

        return new static($executor, $response->sessionId);
    }

    /**
     * Execute a command on the webdriver server.
     * @param CommandInterface $command the command to execute
     *
     * @return mixed the result of the command, the specifics of which depend on the command being executed
     * @throws Exception
     */
    public function execute(CommandInterface $command): mixed
    {
        if ($this->sessionId === null) {
            throw new Exception('Session is not active');
        }

        $response = $this->executor->execute($this->sessionId, $command);

        return $response->value;
    }

    /**
     * Wait synchronously for a condition to be met.
     * This function uses polling to check the condition.
     *
     * @param ConditionInterface $condition the condition to wait for
     * @param int $timeoutSeconds the maximum time to wait for the condition to be met
     * @param int $intervalMs the time to wait between checks
     *
     * @return mixed
     * @throws Exception
     */
    public function wait(
        ConditionInterface $condition,
        int $timeoutSeconds = 10,
        int $intervalMs = 250
    ): mixed {
        $end = microtime(true) + $timeoutSeconds;
        $lastException = null;

        while ($end > microtime(true)) {
            try {
                $result = $condition->apply($this);
                if ($result !== false) {
                    return $result;
                }
            } catch (Exception $e) {
                $lastException = $e;
            }
            usleep($intervalMs * 1000);
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        return false;
    }

    /**
     * Cleanly close the webdriver session.
     *
     * @return void
     * @throws Exception
     */
    public function quit(): void
    {
        if ($this->executor !== null) {
            $this->execute(new Command(CommandName::Quit));
            $this->executor = null;
        }

        $this->sessionId = null;
    }
}
