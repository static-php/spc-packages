<?php

namespace staticphp;

interface package
{
    public function getFpmConfig(): array;

    public function getDebuginfoFpmConfig(): array;

    public function getFpmExtraArgs(): array;
}
