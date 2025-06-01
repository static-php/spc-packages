<?php

namespace staticphp\package;

use staticphp\package;

class cli implements package
{
    public function getFpmConfig(): array
    {
        return [
            'config-files' => [
                '/etc/static-php/php.ini',
            ],
            'provides' => [
                'static-php',
                'php',
            ],
            'files' => [
                __DIR__ . '/php.ini' => '/etc/static-php/php.ini',
                BUILD_BIN_PATH . '/php' => '/usr/static-php/php'
            ]
        ];
    }
}
