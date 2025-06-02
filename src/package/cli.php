<?php

namespace staticphp\package;

use staticphp\CraftConfig;
use staticphp\package;

class cli implements package
{
    public function getFpmConfig(): array
    {
        $craftConfig = CraftConfig::getInstance();

        return [
            'config-files' => [
                '/etc/static-php/php.ini',
            ],
            'provides' => [
                'static-php',
                'php-cli',
                'php',
                ...array_map(fn ($ext) => 'php-' . $ext, $craftConfig->getStaticExtensions())
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/etc/static-php/php.ini',
                BUILD_BIN_PATH . '/php' => '/usr/static-php/php'
            ]
        ];
    }
}
