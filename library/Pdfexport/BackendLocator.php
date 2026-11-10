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
 * PDF backend selection with priority ordering and connection fallback
 */
class BackendLocator
{
    /** @var string Reserved section for explicit backend configuration state. */
    public const GLOBAL_SECTION = '_global';

    /** @var string Setting that prevents implicit defaults after administrative saves. */
    public const CONFIGURED_SETTING = 'configured';

    /**
     * The backend retained for subsequent support checks and exports
     */
    protected ?PfdPrintBackend $backend = null;

    /**
     * Get an independent backend configuration snapshot with legacy defaults
     *
     * Legacy Chrome sections without a type are enabled unless explicitly disabled.
     * Empty configuration retains the implicit local Chrome backend unless an
     * administrative save has marked backend configuration as explicit. Global
     * metadata is excluded from the snapshot. Reading this snapshot never changes
     * the registered configuration or its INI file.
     * The original file path is retained for explicit administrative saves.
     *
     * @return Config
     */
    public static function getBackendConfig(): Config
    {
        $original = Config::module('pdfexport');
        $config = Config::fromArray($original->toArray());
        $config->setConfigFile($original->getConfigFile());

        $config->removeSection(static::GLOBAL_SECTION);

        if (
            $config->isEmpty()
            && $original->get(static::GLOBAL_SECTION, static::CONFIGURED_SETTING, '0') !== '1'
        ) {
            $config->setSection('chrome');
        }

        if ($config->hasSection('chrome') && $config->get('chrome', 'type') === null) {
            $settings = $config->getSection('chrome')->toArray();
            $settings['type'] = $config->get('chrome', 'host') !== null ? 'remote_chrome' : 'local_chrome';
            $settings['enabled'] ??= 'y';
            $settings['priority'] ??= '100';
            $config->setSection('chrome', $settings);
        }

        return $config;
    }

    /**
     * Get the supported backend with the lowest configured priority
     *
     * Cache the first successful backend for subsequent calls while it is supported.
     *
     * @return ?PfdPrintBackend The first supported backend, or null if none is available
     */
    public function getFirstSupportedBackend(): ?PfdPrintBackend
    {
        if ($this->backend !== null && $this->backend->isSupported()) {
            return $this->backend;
        }

        $this->backend = null;
        $sorted = [];
        foreach (static::getBackendConfig() as $section => $configs) {
            if ($configs->get('enabled', 'n') !== 'y') {
                continue;
            }
            $priority = (int) $configs->get('priority', 100);
            $sorted[$section] = $priority;
        }

        asort($sorted);

        foreach ($sorted as $section => $priority) {
            $this->backend = $this->getSingleBackend($section);
            if ($this->backend === null) {
                continue;
            }
            return $this->backend;
        }

        return null;
    }

    /**
     * Create and connect to a WebDriver backend
     *
     * The backend is identified by the 'type' configuration option.
     *
     * @param string $section The configuration section to use for the backend
     *
     * @return ?PfdPrintBackend The connected backend, or null if it could not be created
     */
    protected function connectToWebDriver(string $section): ?PfdPrintBackend
    {
        $config = static::getBackendConfig();
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
            Logger::info("Connected WebDriver backend: $section");
            return $backend;
        } catch (Exception $e) {
            Logger::warning("Webdriver connection failed! backend: $section, error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Connect to a remote HeadlessChrome backend
     *
     * @param string $section The configuration section to use for the backend
     *
     * @return ?PfdPrintBackend The connected backend, or null if it could not be created
     */
    protected function connectToRemoteChrome(string $section): ?PfdPrintBackend
    {
        $config = static::getBackendConfig();
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
            Logger::info("Connected remote Chrome backend: $section");

            return $backend;
        } catch (Exception $e) {
            Logger::warning(
                "Error while creating remote HeadlessChrome! backend: $section, error: " . $e->getMessage(),
            );
        }

        return null;
    }

    /**
     * Connect to a local HeadlessChrome backend
     *
     * @param string $section The configuration section to use for the backend
     *
     * @return ?PfdPrintBackend The connected backend, or null if it could not be created
     */
    protected function connectToLocalChrome(string $section): ?PfdPrintBackend
    {
        $config = static::getBackendConfig();
        $binary = $config->get($section, 'binary', '/usr/bin/google-chrome');
        try {
            if ($binary === null) {
                return null;
            }

            $backend = HeadlessChromeBackend::createLocal(
                $binary,
                $config->get($section, 'force_temp_storage', '0') === '1',
            );
            Logger::info("Connected local Chrome backend: $section");

            return $backend;
        } catch (Exception $e) {
            Logger::warning(
                "Error while creating HeadlessChrome backend: $section, path: $binary, error:" . $e->getMessage(),
            );
        }

        return null;
    }

    /**
     * Create and connect to a single backend
     *
     * The type of the backend is determined by the 'type' configuration option.
     *
     * @param string $section The configuration section to use for the backend
     *
     * @return ?PfdPrintBackend The connected backend, or null if it could not be created
     */
    protected function getSingleBackend(string $section): ?PfdPrintBackend
    {
        $config = static::getBackendConfig();
        if (! $config->hasSection($section)) {
            return null;
        }

        Logger::debug("Connecting to backend $section.");

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
            Logger::warning("Connected backend $section does not support generating PDFs");
            return null;
        }

        return $backend;
    }
}
