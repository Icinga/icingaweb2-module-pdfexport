<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport;

use Exception;
use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Module\Pdfexport\Backend\Chromedriver;
use Icinga\Module\Pdfexport\Backend\Geckodriver;
use Icinga\Module\Pdfexport\Backend\HeadlessChromeBackend;
use Icinga\Module\Pdfexport\Backend\PfdPrintBackend;

/**
 * Class implementing the traversal and fallback logic for the PDF backend selection.
 */
class BackendLocator
{
    /**
     * Get the first supported backend from the configuration which responded with a successful connection.
     * First, in this context means the backend with the lowest priority.
     * @return PfdPrintBackend|null the first supported backend or null if none is available
     */
    public function getFirstSupportedBackend(): ?PfdPrintBackend
    {
        $sorted = [];
        foreach (Config::module('pdfexport') as $section => $configs) {
            $priority = (int) $configs->get('priority', 100);
            $sorted[$section] = $priority;
        }

        asort($sorted);

        foreach ($sorted as $section => $priority) {
            $backend = $this->getSingleBackend($section);
            if ($backend === null) {
                continue;
            }
            return $backend;
        }

        return null;
    }

    /**
     * Create and connect to a WebDriver backend.
     * The backend is identified by the 'type' configuration option.
     * @param string $section The configuration section to use for the backend.
     *
     * @return PfdPrintBackend|null The created and connected backend or null if the backend could not be created.
     */
    protected function connectToWebDriver(string $section): ?PfdPrintBackend
    {
        $config = Config::module('pdfexport');
        try {
            $host = $config->get($section, 'host');
            if ($host === null) {
                return null;
            }
            $port = $config->get($section, 'port', 4444);
            $url = "$host:$port";
            $type = $config->get($section, 'type');
            $backend = match ($type) {
                'chrome_webdriver' => new Chromedriver($url),
                'firefox_webdriver' => new Geckodriver($url),
                default => throw new Exception("Invalid webdriver type $type"),
            };
            Logger::info("Connected WebDriver Backend: $section");
            return $backend;
        } catch (Exception $e) {
            Logger::warning("Webdriver connection failed! backend: $section, error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Connect to a remote HeadlessChrome backend.
     * @param string $section the configuration section to use for the backend
     *
     * @return PfdPrintBackend|null the created and connected backend or null if the backend could not be created
     */
    protected function connectToRemoteChrome(string $section): ?PfdPrintBackend
    {
        $config = Config::module('pdfexport');
        try {
            $host = $config->get($section, 'host');
            if ($host === null) {
                return null;
            }
            $port = $config->get($section, 'port', 9222);
            $backend = HeadlessChromeBackend::createRemote(
                $host,
                $port,
            );
            Logger::info("Connected WebDriver Backend: $section");
            return $backend;
        } catch (Exception $e) {
            Logger::warning(
                "Error while creating remote HeadlessChrome! backend: $section, error: " . $e->getMessage(),
            );
        }
        return null;
    }

    /**
     * Connect to a local HeadlessChrome backend.
     * @param string $section the configuration section to use for the backend
     *
     * @return PfdPrintBackend|null the created and connected backend or null if the backend could not be created
     */
    protected function connectToLocalChrome(string $section): ?PfdPrintBackend
    {
        $config = Config::module('pdfexport');
        $binary = $config->get($section, 'binary', '/usr/bin/google-chrome');
        try {
            if ($binary === null) {
                return null;
            }
            $backend = HeadlessChromeBackend::createLocal(
                $binary,
                Config::module('pdfexport')->get('chrome', 'force_temp_storage', '0') === '1',
            );
            Logger::info("Connected WebDriver Backend: $section");
            return $backend;
        } catch (Exception $e) {
            Logger::warning(
                "Error while creating HeadlessChrome backend: $section, path: $binary, error:" . $e->getMessage(),
            );
        }
        return null;
    }

    /**
     * Create and connect to a single backend.
     * The type of the backend is determined by the 'type' configuration option.
     * @param $section string the configuration section to use for the backend
     *
     * @return PfdPrintBackend|null the created and connected backend or null if the backend could not be created
     */
    protected function getSingleBackend(string $section): ?PfdPrintBackend
    {
        $config = Config::module('pdfexport');
        if (! $config->hasSection($section)) {
            return null;
        }

        Logger::info("Connecting to backend $section.");

        $type = $config->get($section, 'type');
        $backend = match ($type) {
            'local_chrome' => $this->connectToLocalChrome($section),
            'remote_chrome' => $this->connectToRemoteChrome($section),
            default => $this->connectToWebDriver($section),
        };

        if ($backend === null) {
            Logger::warning("Failed to connect to backend: $section");
            return null;
        }

        if (! $backend->isSupported()) {
            Logger::info("Connected backend $section reports that it doesn't support generating PDFs");
            return null;
        }

        return $backend;
    }
}
