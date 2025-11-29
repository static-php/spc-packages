<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\step\CreatePackages;

class embed implements package
{
    public function getName(): string
    {
        return CreatePackages::getPrefix() . '-' . $this->name;
    }

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

    public function getDebuginfoFpmConfig(): array
    {
        $phpVersionDigits = str_replace('.', '', SPP_PHP_VERSION);
        $libName = 'lib' . CreatePackages::getPrefix() . "-{$phpVersionDigits}.so";
        $src = BUILD_ROOT_PATH . '/debug/' . $libName . '.debug';
        if (!file_exists($src)) {
            return [];
        }
        $target = '/usr/lib/debug' . getLibdir() . '/' . $libName . '.debug';
        return [
            'depends' => [CreatePackages::getPrefix() . '-embed'],
            'files' => [
                $src => $target,
            ],
        ];
    }
}
