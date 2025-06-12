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
                'php-fpm',
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/etc/php-zts.ini',
                INI_PATH . '/php-fpm.conf' => '/etc/php-zts-fpm.conf',
                INI_PATH . '/www.conf' => '/etc/php-zts-fpm.d/conf',
                BUILD_BIN_PATH . '/php-fpm' => '/usr/bin/php-zts-fpm',
            ],
            'empty_directories' => [
                '/etc/php-zts-fpm.d/',
                '/var/lib/php-zts/session',
                '/var/lib/php-zts/wsdlcache',
                '/var/lib/php-zts/opcache',
            ],
            'directories' => [
                '/etc/php-zts-fpm.d/',
                '/var/lib/php-zts/session',
                '/var/lib/php-zts/wsdlcache',
                '/var/lib/php-zts/opcache',
            ],
        ];
    }
}
