<?php

namespace Icinga\Module\Pdfexport\Form;

use Exception;
use Icinga\Application\Config;
use Icinga\Module\Pdfexport\Web\ShowConfiguration;
use ipl\Web\Compat\CompatForm;

class ConfigForm extends CompatForm
{
    protected array $ignoredElements = [];

    public function __construct(
        protected Config $config,
        protected ?string $section = null,
    ) {
    }

    public function ensureAssembled(): static
    {
        if (! $this->hasBeenAssembled) {
            parent::ensureAssembled();
            $this->populateFromConfig();
        }

        return $this;
    }

    protected function populateFromConfig(): void
    {
        foreach ($this->getElements() as $element) {
            [$section, $key] = $this->getIniKeyFromName($element->getName());
            if ($section === null && $key === null) {
                continue;
            }
            $value = $this->getPopulatedValue($element->getName()) ?? $this->config->get($section, $key);
            $this->populate([
                $element->getName() => $value,
            ]);
        }
    }

    protected function getIniKeyFromName(string $name): ?array
    {
        if ($this->section !== null) {
            return [$this->section, $name];
        }

        $parts = explode('_', $name, 2);
        if (count($parts) !== 2) {
            return [null, null];
        }

        return $parts;
    }

    public function getConfigValue(string $name, $default = null): mixed
    {
        if (! $this->hasElement($name)) {
            return $default;
        }

        if (($value = $this->getPopulatedValue($name)) !== null) {
            return $value;
        }

        [$section, $key] = $this->getIniKeyFromName($name);
        if ($section === null && $key === null) {
            return $default;
        }

        if (! $this->config->hasSection($section)) {
            return $default;
        }

        return $this->config->get($section, $key, $default);
    }

    public function getConfigValues(): array
    {
        $values = [];
        foreach ($this->getElements() as $element) {
            if ($element->isIgnored()) {
                continue;
            }

            $values[$element->getName()] = $this->getConfigValue($element->getName());
        }

        return $values;
    }

    protected function onSuccess(): void
    {
        foreach ($this->getElements() as $element) {
            if (in_array($element->getName(), $this->ignoredElements)) {
                continue;
            }
            [$section, $key] = $this->getIniKeyFromName($element->getName());
            if ($section === null || $key === null) {
                continue;
            }
            $value = $this->getConfigValue($element->getName());

            $configSection = $this->config->getSection($section);
            if (empty($value)) {
                unset($configSection[$key]);
            } else {
                $configSection->$key = $value;
            }

            if ($configSection->isEmpty()) {
                $this->config->removeSection($section);
            } else {
                $this->config->setSection($section, $configSection);
            }
        }

        try {
            $this->config->saveIni();
        } catch (Exception $e) {
            $content = $this->getContent();
            array_unshift(
                $content,
                new ShowConfiguration(
                    $this->config->getConfigFile(),
                    $this->config,
                )
            );
            $this->setContent($content);
            throw $e;
        }
    }
}
