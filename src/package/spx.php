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
                getConfdir() . '/conf.d/20-spx.ini',
            ],
            'depends' => [
                CreatePackages::getPrefix() . '-cli'
            ],
            'files' => [
                BUILD_MODULES_PATH . '/spx.so' => getLibdir() . '/' . CreatePackages::getPrefix() . '/modules/spx.so',
                $this->getIniPath() => getConfdir() . '/conf.d/20-spx.ini',
                BUILD_ROOT_PATH . '/share/misc/php-spx/assets/web-ui' => '/usr/share/php-zts/misc/php-spx/assets/web-ui',
            ]
        ];
    }
}
