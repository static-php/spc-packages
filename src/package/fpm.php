<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\step\CreatePackages;

class fpm implements package
{
    public function getFpmConfig(): array
    {
        return [
            'depends' => [
                CreatePackages::getPrefix() . '-cli',
            ],
            'files' => [
                INI_PATH . '/php-fpm.conf' => '/etc/php-zts/php-fpm.conf',
                INI_PATH . '/www.conf' => '/etc/php-zts/fpm.d/www.conf',
                INI_PATH . '/php-fpm.service' => '/usr/lib/systemd/system/php-zts-fpm.service',
                BUILD_BIN_PATH . '/php-fpm' => '/usr/sbin/php-zts-fpm',
            ],
            'empty_directories' => [
                '/etc/php-zts/fpm.d/',
                '/var/log/php-zts/php-fpm',
            ],
            'directories' => [
                '/etc/php-zts/fpm.d/',
                '/var/log/php-zts/php-fpm',
            ],
        ];
    }

    public function getFpmExtraArgs(): array
    {
        return [];
    }
}
