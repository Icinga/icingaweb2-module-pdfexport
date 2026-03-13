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

class BackendLocator
{
    public function getFirstSupportedBackend(): ?PfdPrintBackend
    {
        foreach (['webdriver', 'remote_chrome', 'local_chrome'] as $section) {
            $backend = $this->getSingleBackend($section);
            if ($backend === null) {
                continue;
            }
            return $backend;
        }

        return null;
    }

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
                'chrome' => new Chromedriver($url),
                'firefox' => new Geckodriver($url),
                default => throw new Exception("Invalid webdriver type $type"),
            };
            Logger::info("Connected WebDriver Backend: $section");
            return $backend;
        } catch (Exception $e) {
            Logger::warning("Webdriver connection failed! backend: $section, error: " . $e->getMessage());
        }
        return null;
    }

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
                $this->getForceTempStorage(),
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

    protected function getSingleBackend($section): ?PfdPrintBackend
    {
        $config = Config::module('pdfexport');
        if (! $config->hasSection($section)) {
            return null;
        }

        Logger::info("Connecting to backend $section.");

        $backend = match ($section) {
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

    public static function getForceTempStorage(): bool
    {
        $value = Config::module('pdfexport')->get('chrome', 'force_temp_storage', 'n');
        return in_array($value, ['1', 'y']);
    }
}
