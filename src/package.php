<?php

namespace staticphp;

interface package
{
    public function getFpmConfig(string $version, string $iteration): array;
}
