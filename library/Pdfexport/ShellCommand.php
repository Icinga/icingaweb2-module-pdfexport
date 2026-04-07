<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport;

use Exception;

class ShellCommand
{
    /** @var string Command to execute */
    protected string $command;

    /** @var ?int Exit code of the command */
    protected ?int $exitCode = null;

    /** @var array|null Environment variables */
    protected ?array $env;

    /** @var ?resource Process resource */
    protected $resource;

    /** @var object|null Named pipe resources */
    protected ?object $namedPipes;

    /** @var string collected stdout */
    protected string $stdout;

    /** @var string collected stderr */
    protected string $stderr;

    /**
     * Create a new command
     *
     * @param string $command The command to execute
     * @param bool $escape Whether to escape the command
     * @param array|null $env Environment variables
     */
    public function __construct(string $command, bool $escape = true, ?array $env = null)
    {
        $this->command = $escape ? escapeshellcmd($command) : $command;
        $this->env = $env;
    }

    /**
     * Get the exit code of the command
     *
     * @return int
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * Get the status of the command
     *
     * @return object
     */
    public function getStatus(): object
    {
        $status = (object) proc_get_status($this->resource);
        if ($status->running === false && $this->exitCode === null) {
            // The exit code is only valid the first time proc_get_status is
            // called in terms of running false, hence we capture it
            $this->exitCode = $status->exitcode;
        }

        return $status;
    }

    public function start(): void
    {
        if ($this->resource !== null) {
            throw new Exception('Command already started');
        }

        $descriptors = [
            ['pipe', 'r'], // stdin
            ['pipe', 'w'], // stdout
            ['pipe', 'w'], // stderr
        ];

        $this->resource = proc_open(
            $this->command,
            $descriptors,
            $pipes,
            null,
            $this->env,
        );

        if (! is_resource($this->resource)) {
            throw new Exception(sprintf(
                "Can't fork '%s'",
                $this->command,
            ));
        }

        $this->namedPipes = (object) [
            'stdin' => &$pipes[0],
            'stdout' => &$pipes[1],
            'stderr' => &$pipes[2],
        ];

        fclose($this->namedPipes->stdin);
    }

    public function wait($callback = null): void
    {
        if ($this->resource === null) {
            throw new Exception('Command not started');
        }

        $read = [$this->namedPipes->stderr, $this->namedPipes->stdout];
        $origRead = $read;
        // stdin not handled
        $write = null;
        $except = null;
        $this->stdout = '';
        $this->stderr = '';

        stream_set_blocking($this->namedPipes->stdout, false);
        stream_set_blocking($this->namedPipes->stderr, false);

        while (stream_select($read, $write, $except, 0, 20000) !== false) {
            foreach ($read as $pipe) {
                if ($pipe === $this->namedPipes->stdout) {
                    $this->stdout .= stream_get_contents($pipe);
                }

                if ($pipe === $this->namedPipes->stderr) {
                    $this->stderr .= stream_get_contents($pipe);
                }
            }

            foreach ($origRead as $i => $str) {
                if (feof($str)) {
                    unset($origRead[$i]);
                }
            }

            if (empty($origRead)) {
                break;
            }

            // Reset pipes
            $read = $origRead;

            if ($callback !== null) {
                $continue = call_user_func($callback, $this->stdout, $this->stderr);
                if ($continue === false) {
                    break;
                }
            }
        }
    }

    public function stop(): int
    {
        if ($this->resource === null) {
            throw new Exception('Command not started');
        }
        fclose($this->namedPipes->stderr);
        fclose($this->namedPipes->stdout);

        proc_terminate($this->resource);

        $exitCode = proc_close($this->resource);
        if ($this->exitCode === null) {
            $this->exitCode = $exitCode;
        }

        $this->resource = null;
        $this->namedPipes = null;

        return $exitCode;
    }
}
