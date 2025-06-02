<?php

namespace staticphp\package;

use staticphp\CraftConfig;
use staticphp\package;

class staticphp implements package
{
    public function getFpmConfig(): array
    {
        $craftConfig = CraftConfig::getInstance();

        return [
            'provides' => [
                'static-php',
            ],
            'depends' => [
                'php-cli',
                'php-fpm',
                'php-embed',
                ...array_map(fn ($ext) => 'php-' . $ext, $craftConfig->getStaticExtensions())
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/dev/null'
            ]
        ];
    }
}
