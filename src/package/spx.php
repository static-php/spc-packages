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
                '/etc/static-php/php.d/spx.ini',
            ],
            'depends' => [
                CreatePackages::getPrefix() . '-cli'
            ],
            'files' => [
                BUILD_MODULES_PATH . '/spx.so' => '/usr/lib/static-php/modules/spx.so',
                $this->getIniPath() => '/etc/static-php/php.d/spx.ini',
                BUILD_ROOT_PATH . '/share/misc/php-spx/assets/web-ui' => '/usr/share/static-php/misc/php-spx/assets/web-ui',
            ]
        ];
    }
}
