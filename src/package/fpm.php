<?php

namespace staticphp\package;

use staticphp\package;

class fpm implements package
{
    public function getFpmConfig(): array
    {
        return [
            'config-files' => [
                '/etc/static-php/php.ini',
            ],
            'provides' => [
                'static-php',
                'php-fpm',
            ],
            'files' => [
                __DIR__ . '/php.ini' => '/etc/static-php/php.ini',
                __DIR__ . '/php-fpm.conf' => '/etc/static-php/php-fpm.conf',
                BUILD_BIN_PATH . '/php-fpm' => '/usr/static-php/php-fpm',
                '' => [
                    '/etc/static-php/php-fpm.d/',
                    '/var/lib/static-php/session',
                    '/var/lib/static-php/wsdlcache',
                    '/var/lib/static-php/opcache',
                ]
            ]
        ];

    }
}
