<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\WebDriver;

/**
 * A container for WebDriver capabilities.
 * @link https://www.w3.org/TR/webdriver/#capabilities
 */
class Capabilities
{
    /** @var array */
    private static array $ossToW3c = [
        'platform' => 'platformName',
        'version' => 'browserVersion',
        'acceptSslCerts' => 'acceptInsecureCerts',
    ];

    /**
     * Construct a new Capabilities set.
     * @param array $capabilities The capabilities to set.
     */
    public function __construct(
        protected array $capabilities = [],
    ) {
    }

    /**
     * Create a new Capabilities set with the default capabilities for Chrome.
     * @return static
     */
    public static function chrome(): static
    {
        return new static([
            'browserName' => 'chrome',
            'platform' => 'ANY',
        ]);
    }

    /**
     * Create a new Capabilities set with the default capabilities for Firefox.
     * @return static
     */
    public static function firefox(): static
    {
        return new static([
            'browserName' => 'firefox',
            'platform' => 'ANY',
            'moz:firefoxOptions' => [
                'prefs' => [
                    'reader.parse-on-load.enabled' => false,
                    'devtools.jsonview.enabled' => false,
                ],
            ],
        ]);
    }

    /**
     * Convert the capabilities to a W3C-compatible array.
     * @return array
     */
    public function toW3cCompatibleArray(): array
    {
        $allowedW3cCapabilities = [
            'browserName',
            'browserVersion',
            'platformName',
            'acceptInsecureCerts',
            'pageLoadStrategy',
            'proxy',
            'setWindowRect',
            'timeouts',
            'strictFileInteractability',
            'unhandledPromptBehavior',
        ];

        $capabilities = $this->toArray();
        $w3cCapabilities = [];

        foreach ($capabilities as $capabilityKey => $capabilityValue) {
            if (in_array($capabilityKey, $allowedW3cCapabilities, true)) {
                $w3cCapabilities[$capabilityKey] = $capabilityValue;
                continue;
            }

            if (array_key_exists($capabilityKey, self::$ossToW3c)) {
                if ($capabilityKey === 'platform') {
                    if ($capabilityValue === 'ANY') {
                        continue;
                    }

                    $w3cCapabilities[self::$ossToW3c[$capabilityKey]] = mb_strtolower($capabilityValue);
                } else {
                    $w3cCapabilities[self::$ossToW3c[$capabilityKey]] = $capabilityValue;
                }
            }

            if (mb_strpos($capabilityKey, ':') !== false) {
                $w3cCapabilities[$capabilityKey] = $capabilityValue;
            }
        }

        if (array_key_exists('goog:chromeOptions', $capabilities)) {
            $w3cCapabilities['goog:chromeOptions'] = $capabilities['goog:chromeOptions'];
        }

        if (array_key_exists('firefox_profile', $capabilities)) {
            if (
                ! array_key_exists('moz:firefoxOptions', $capabilities)
                || ! array_key_exists('profile', $capabilities['moz:firefoxOptions'])
            ) {
                $w3cCapabilities['moz:firefoxOptions']['profile'] = $capabilities['firefox_profile'];
            }
        }

        return $w3cCapabilities;
    }

    /**
     * Get the raw capabilities array. This is not necessarily W3C-compatible.
     * @return array
     */
    public function toArray(): array
    {
        return $this->capabilities;
    }
}
