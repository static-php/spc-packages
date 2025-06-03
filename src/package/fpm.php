<?php

namespace staticphp\package;

use staticphp\package;

class fpm implements package
{
    public function getFpmConfig(string $version, string $iteration): array
    {
        return [
            'config-files' => [
                '/etc/static-php/php.ini',
            ],
            'provides' => [
                'php-fpm',
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/etc/static-php/php.ini',
                INI_PATH . '/php-fpm.conf' => '/etc/static-php/php-fpm.conf',
                INI_PATH . '/www.conf' => '/etc/static-php/php-fpm.d/conf',
                BUILD_BIN_PATH . '/php-fpm' => '/usr/static-php/bin/php-fpm',
            ],
            'empty_directories' => [
                '/etc/static-php/php-fpm.d/',
                '/var/lib/static-php/session',
                '/var/lib/static-php/wsdlcache',
                '/var/lib/static-php/opcache',
            ],
            'directories' => [
                '/etc/static-php/php-fpm.d/',
                '/var/lib/static-php/session',
                '/var/lib/static-php/wsdlcache',
                '/var/lib/static-php/opcache',
            ],
        ];
    }
}
