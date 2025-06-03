<?php

namespace staticphp\package;

use staticphp\package;

class staticphp implements package
{
    public function getFpmConfig(): array
    {
        return [
            'provides' => [
                'static-php',
            ],
            'depends' => [
                'static-php-cli',
                'static-php-fpm',
                'static-php-embed',
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/dev/null'
            ]
        ];
    }
}
