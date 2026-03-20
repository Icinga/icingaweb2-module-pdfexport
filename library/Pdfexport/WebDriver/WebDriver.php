<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

use Exception;

class WebDriver
{
    protected function __construct(
        protected ?CommandExecutor $executor,
        protected ?string $sessionId,
    ) {
    }

    public function __destruct()
    {
        $this->quit();
    }

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

        return new static($executor, $response->getSessionID());
    }

    public function execute(CommandInterface $command): mixed
    {
        if ($this->sessionId === null) {
            throw new Exception('Session is not active');
        }

        $response = $this->executor->execute($this->sessionId, $command);

        return $response->getValue();
    }

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

    public function quit(): void
    {
        if ($this->executor !== null) {
            $this->execute(new Command(CommandName::Quit));
            $this->executor = null;
        }

        $this->sessionId = null;
    }
}
