<?php

namespace staticphp\package;

use staticphp\extension;
use staticphp\step\CreatePackages;

class imagick extends extension
{
    public function getFpmConfig(): array
    {
        return [
            'config-files' => [
                '/etc/php-zts.d/imagick.ini',
            ],
            'depends' => [
                CreatePackages::getPrefix() . '-cli',
                'libgomp.so.1'
            ],
            'files' => [
                BUILD_MODULES_PATH . '/imagick.so' => '/usr/lib64/php-zts/modules/imagick.so',
                $this->getIniPath() => '/etc/php-zts.d/imagick.ini',
            ]
        ];
    }
}
