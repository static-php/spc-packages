<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\step\CreatePackages;

class embed implements package
{
    public function getFpmConfig(): array
    {
        $phpVersion = str_replace('.', '', SPP_PHP_VERSION);
        $name = 'lib' . CreatePackages::getPrefix() . "-$phpVersion.so";
        return [
            'depends' => [
                CreatePackages::getPrefix() . '-cli',
            ],
            'provides' => [
                $name,
                CreatePackages::getPrefix() . '-embedded'
            ],
            'files' => [
                BUILD_LIB_PATH . '/' . $name => getLibdir() . '/' . $name,
            ]
        ];
    }

    public function getFpmExtraArgs(): array
    {
        return [];
    }
}
