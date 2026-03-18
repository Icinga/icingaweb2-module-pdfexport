<?php

namespace Icinga\Module\Pdfexport\WebDriver;

interface ConditionInterface
{
    public function apply(WebDriver $driver): mixed;
}
