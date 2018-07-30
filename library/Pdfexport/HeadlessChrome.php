<?php

namespace Icinga\Module\Pdfexport;

use Icinga\File\Storage\TemporaryLocalFileStorage;

class HeadlessChrome
{
    /** @var string Path to the Chrome binary */
    protected $binary;

    /** @var string Target Url */
    protected $url;

    /** @var TemporaryLocalFileStorage */
    protected $fileStorage;

    public function __construct()
    {
        $this->fileStorage = new TemporaryLocalFileStorage();
    }

    /**
     * Get the path to the Chrome binary
     *
     * @return  string
     */
    public function getBinary()
    {
        return $this->binary;
    }

    /**
     * Set the path to the Chrome binary
     *
     * @param   string  $binary
     *
     * @return  $this
     */
    public function setBinary($binary)
    {
        $this->binary = $binary;

        return $this;
    }

    /**
     * Render the given argument name-value pairs as shell-escaped string
     *
     * @param   array   $arguments
     *
     * @return  string
     */
    public static function renderArgumentList(array $arguments)
    {
        $list = [];

        foreach ($arguments as $name => $value) {
            if ($value !== null) {
                $value = escapeshellarg($value);

                if (! is_int($name)) {
                    if (substr($name, -1) === '=') {
                        $glue = '';
                    } else {
                        $glue = ' ';
                    }

                    $list[] = escapeshellarg($name) . $glue . $value;
                } else {
                    $list[] = $value;
                }
            } else {
                $list[] = escapeshellarg($name);
            }
        }

        return implode(' ', $list);
    }

    /**
     * Get the target Url
     *
     * @return  string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the target Url
     *
     * @param   string  $url
     *
     * @return  $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Use the given HTML string as input
     *
     * @param   string  $html
     * @param   bool    $asFile
     *
     * @return  $this
     */
    public function fromHtml($html, $asFile = true)
    {
        if ($asFile) {
            $path = uniqid('icingaweb2-pdfexport-') . '.html';

            $this->fileStorage->create($path, $html);

            $path = $this->fileStorage->resolvePath($path, true);

            $this->setUrl("file://$path");
        } else {
            $this->setUrl('data:text/html,' . rawurlencode($html));
        }

        return $this;
    }

    /**
     * Export to PDF
     *
     * @param   $filename
     *
     * @return  string
     */
    public function toPdf($filename)
    {
        $path = uniqid('icingaweb2-pdfexport-') . $filename;

        $this->fileStorage->create($path, '');

        $path = $this->fileStorage->resolvePath($path, true);

        $arguments = [
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--print-to-pdf=' => $path,
            $this->getUrl()
        ];

        $command = new ShellCommand(
            escapeshellarg($this->getBinary()) . ' ' . static::renderArgumentList($arguments),
            false
        );

        $command->execute();

        return $path;
    }

    /**
     * Get the major version number of Chrome or false on failure
     *
     * @return  bool|int
     */
    public function getVersion()
    {
        $command = new ShellCommand(
            escapeshellarg($this->getBinary()) . ' ' . static::renderArgumentList(['--version']),
            false
        );

        $output = $command->execute();

        if ($command->getExitCode() !== 0) {
            return false;
        }

        $parts = explode(' ', trim($output->stdout));

        $version = (int) array_pop($parts);

        return $version;
    }
}
