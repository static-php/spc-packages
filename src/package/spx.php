<?php

namespace staticphp\package;

use staticphp\extension;
use staticphp\step\CreatePackages;

class spx extends extension
{
    public function getFpmConfig(): array
    {
        return [
            'config-files' => [
                '/etc/php-zts/conf.d/20-spx.ini',
            ],
            'depends' => [
                CreatePackages::getPrefix() . '-cli'
            ],
            'files' => [
                BUILD_MODULES_PATH . '/spx.so' => '/usr/lib64/php-zts/modules/spx.so',
                $this->getIniPath() => '/etc/php-zts/conf.d/20-spx.ini',
                BUILD_ROOT_PATH . '/share/misc/php-spx/assets/web-ui' => '/usr/share/php-zts/misc/php-spx/assets/web-ui',
            ]
        ];
    }
}
