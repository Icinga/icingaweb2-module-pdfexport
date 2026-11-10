<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

use Exception;
use Throwable;

/**
 * Partial implementation of the WebDriver protocol
 *
 * A session accepts commands until quit() is called. Closing is permanent,
 * even when the remote server rejects session deletion.
 *
 * @see https://www.w3.org/TR/webdriver/
 */
class WebDriver
{
    /**
     * Create a new WebDriver
     *
     * @param ?CommandExecutor $executor Transport for commands to the server
     * @param ?string $sessionId Session identifier returned by session creation
     */
    protected function __construct(
        protected ?CommandExecutor $executor,
        protected ?string $sessionId,
    ) {
    }

    /**
     * Release the session without propagating cleanup failures
     *
     * @return void
     */
    public function __destruct()
    {
        try {
            $this->quit();
        } catch (Throwable) {
            // A failed remote deletion must not interrupt object destruction.
        }
    }

    /**
     * Create a session with the requested browser capabilities
     *
     * @param string $url WebDriver server URL
     * @param Capabilities $capabilities Capabilities to use for the session
     *
     * @return static
     *
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
     * Execute a command on the active session
     *
     * @param CommandInterface $command Command to execute
     *
     * @return mixed Command-specific result
     *
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
     * Wait synchronously for a condition by polling
     *
     * A condition succeeds when it returns anything except false. The last
     * condition exception is rethrown if polling times out.
     *
     * @param ConditionInterface $condition Condition to wait for
     * @param int $timeoutSeconds Maximum wait in seconds
     * @param int $intervalMs Delay between checks in milliseconds
     *
     * @return mixed Condition result, or false when polling times out without an exception
     *
     * @throws Exception
     */
    public function wait(
        ConditionInterface $condition,
        int $timeoutSeconds = 10,
        int $intervalMs = 250,
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
     * End the session permanently
     *
     * State is cleared even if the remote deletion fails. Repeated calls do
     * nothing, and subsequent commands fail because the session is inactive.
     *
     * @return void
     *
     * @throws Exception
     */
    public function quit(): void
    {
        try {
            if ($this->executor !== null && $this->sessionId !== null) {
                $this->execute(new Command(CommandName::Quit));
            }
        } finally {
            $this->executor = null;
            $this->sessionId = null;
        }
    }
}
