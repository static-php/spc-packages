<?php

namespace staticphp\package;

use staticphp\package;

class staticphp implements package
{
    public function getFpmConfig(string $version, string $iteration): array
    {
        return [
            'provides' => [
                'static-php',
            ],
            'depends' => [
                'php-cli',
                'php-fpm',
                'php-embed',
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/dev/null'
            ]
        ];
    }
}
