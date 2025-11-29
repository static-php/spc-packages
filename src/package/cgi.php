<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\step\CreatePackages;

class cgi implements package
{
    public function getName(): string
    {
        return CreatePackages::getPrefix() . '-' . $this->name;
    }

    public function getFpmConfig(): array
    {
        return [
            'depends' => [
                CreatePackages::getPrefix() . '-cli',
            ],
            'files' => [
                BUILD_BIN_PATH . '/php-cgi' => '/usr/bin/php-cgi-zts',
            ]
        ];
    }

    public function getFpmExtraArgs(): array
    {
        return [];
    }

    public function getDebuginfoFpmConfig(): array
    {
        $src = BUILD_ROOT_PATH . '/debug/php-cgi-zts.debug';
        if (!file_exists($src)) {
            return [];
        }
        return [
            'depends' => [CreatePackages::getPrefix() . '-cgi'],
            'files' => [
                $src => '/usr/lib/debug/usr/bin/php-cgi-zts.debug',
            ],
        ];
    }
}
