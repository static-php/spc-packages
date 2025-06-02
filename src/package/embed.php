<?php

namespace staticphp\package;

use staticphp\package;

class embed implements package
{
    public function getFpmConfig(): array
    {
        return [
            'config-files' => [
                '/etc/static-php/php.ini',
            ],
            'provides' => [
                'static-php',
                'libphp.so',
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/etc/static-php/php.ini',
                BUILD_LIB_PATH . '/libphp.so' => '/usr/static-php/libphp.so',
            ]
        ];

    }
}
