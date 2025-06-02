<?php

namespace staticphp\package;

use staticphp\package;

class php implements package
{
    public function getFpmConfig(): array
    {
        return [
            'provides' => [
                'static-php',
                'php',
            ],
            'depends' => [
                'php-cli',
                'php-fpm',
                'php-embed',
            ],
            'files' => [
                INI_PATH . '/static-php.sh' => '/etc/profile.d/static-php.sh',
            ]
        ];
    }
}
