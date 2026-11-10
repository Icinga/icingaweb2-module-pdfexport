<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Pdfexport\Lib;

use Icinga\Application\Config;
use Icinga\Test\BaseTestCase;

abstract class ConfigTestCase extends BaseTestCase
{
    /** @var array<string, array<string, mixed>> */
    protected array $originalConfig;

    protected ?string $originalConfigFile;

    public function setUp(): void
    {
        parent::setUp();

        $config = Config::module('pdfexport');
        $this->originalConfig = $config->toArray();
        $this->originalConfigFile = $config->getConfigFile();
    }

    public function tearDown(): void
    {
        $this->replaceConfig($this->originalConfig)->setConfigFile($this->originalConfigFile);

        parent::tearDown();
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     *
     * @return Config
     */
    protected function replaceConfig(array $sections): Config
    {
        $config = Config::module('pdfexport');
        foreach (array_keys($config->toArray()) as $section) {
            $config->removeSection($section);
        }

        foreach ($sections as $section => $settings) {
            $config->setSection($section, $settings);
        }

        return $config;
    }
}
