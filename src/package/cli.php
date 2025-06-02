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
                'php',
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/etc/static-php/php.ini',
                BUILD_BIN_PATH . '/php' => '/usr/static-php/bin/php',
                INI_PATH . '/static-php.sh' => '/etc/profile.d/static-php.sh',
            ]
        ];
    }
}
