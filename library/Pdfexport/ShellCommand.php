<?php

namespace Icinga\Module\Pdfexport;

class ShellCommand
{
    /** @var string Command to execute */
    protected $command;

    /** @var int Exit code of the command */
    protected $exitCode;

    /** @var resource Process resource */
    protected $resource;

    /**
     * Create a new command
     *
     * @param   string  $command    The command to execute
     * @param   bool    $escape     Whether to escape the command
     */
    public function __construct($command, $escape = true)
    {
        $command = (string) $command;

        $this->command = $escape ? escapeshellcmd($command) : $command;
    }

    /**
     * Get the exit code of the command
     *
     * @return int
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * Get the status of the command
     *
     * @return object
     */
    public function getStatus()
    {
        $status = (object) proc_get_status($this->resource);
        if ($status->running === false
            && $this->exitCode === null
        ) {
            // The exit code is only valid the first time proc_get_status is
            // called in terms of running false, hence we capture it
            $this->exitCode = $status->exitcode;
        }
        return $status;
    }

    /**
     * Execute the command
     *
     * @return  object
     *
     * @throws  \Exception
     */
    public function execute()
    {
        if ($this->resource !== null) {
            throw new \Exception('Command already started');
        }

        $descriptors = [
            ['pipe', 'r'], // stdin
            ['pipe', 'w'], // stdout
            ['pipe', 'w']  // stderr
        ];

        $this->resource = proc_open(
            $this->command,
            $descriptors,
            $pipes
        );

        if (! is_resource($this->resource)) {
            throw new \Exception(sprintf(
                "Can't fork '%s'",
                $this->command
            ));
        }

        $namedpipes = (object) [
            'stdin'     => &$pipes[0],
            'stdout'    => &$pipes[1],
            'stderr'    => &$pipes[2]
        ];

        fclose($namedpipes->stdin);

        $read = [$namedpipes->stderr, $namedpipes->stdout];
        $origRead = $read;
        $write = null; // stdin not handled
        $except = null;
        $stdout = '';
        $stderr = '';

        stream_set_blocking($namedpipes->stdout, 0); // non-blocking
        stream_set_blocking($namedpipes->stderr, 0);

        while (stream_select($read, $write, $except, null, 20000) !== false) {
            foreach ($read as $pipe) {
                if ($pipe === $namedpipes->stdout) {
                    $stdout .= stream_get_contents($pipe);
                }

                if ($pipe === $namedpipes->stderr) {
                    $stderr .= stream_get_contents($pipe);
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
        }

        fclose($namedpipes->stderr);
        fclose($namedpipes->stdout);

        $exitCode = proc_close($this->resource);
        if ($this->exitCode === null) {
            $this->exitCode = $exitCode;
        }

        $this->resource = null;

        return (object) [
            'stdout' => $stdout,
            'stderr' => $stderr
        ];
    }
}
