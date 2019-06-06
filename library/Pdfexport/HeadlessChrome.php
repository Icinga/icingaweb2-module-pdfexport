<?php
// Icinga PDF Export | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\Pdfexport;

use Icinga\File\Storage\StorageInterface;
use Icinga\File\Storage\TemporaryLocalFileStorage;

class HeadlessChrome
{
    /** @var string Path to the Chrome binary */
    protected $binary;

    /** @var string Target Url */
    protected $url;

    /** @var StorageInterface */
    protected $fileStorage;

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
     * Get the file storage
     *
     * @return  StorageInterface
     */
    public function getFileStorage()
    {
        if ($this->fileStorage === null) {
            $this->fileStorage = new TemporaryLocalFileStorage();
        }

        return $this->fileStorage;
    }

    /**
     * Set the file storage
     *
     * @param   StorageInterface  $fileStorage
     *
     * @return  $this
     */
    public function setFileStorage($fileStorage)
    {
        $this->fileStorage = $fileStorage;

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
            $storage = $this->getFileStorage();

            $storage->create($path, $html);

            $path = $storage->resolvePath($path, true);

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
     *
     * @throws  \Exception
     */
    public function toPdf($filename)
    {
        $path = uniqid('icingaweb2-pdfexport-') . $filename;
        $storage = $this->getFileStorage();

        $storage->create($path, '');

        $path = $storage->resolvePath($path, true);

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

        $output = $command->execute();

        if ($command->getExitCode() !== 0) {
            throw new \Exception($output->stderr);
        }

        return $path;
    }

    /**
     * Get the major version number of Chrome or false on failure
     *
     * @return  int
     *
     * @throws  \Exception
     */
    public function getVersion()
    {
        $command = new ShellCommand(
            escapeshellarg($this->getBinary()) . ' ' . static::renderArgumentList(['--version']),
            false
        );

        $output = $command->execute();

        if ($command->getExitCode() !== 0) {
            throw new \Exception($output->stderr);
        }

        $parts = explode(' ', trim($output->stdout));

        $version = (int) array_pop($parts);

        return $version;
    }
}
