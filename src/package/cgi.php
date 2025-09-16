<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\step\CreatePackages;

class cgi implements package
{
    public function getFpmConfig(): array
    {
        return [
            'depends' => [
                CreatePackages::getPrefix() . '-cli',
            ],
            'files' => [
                BUILD_BIN_PATH . '/php-cgi' => '/usr/bin/php-zts-cgi',
            ]
        ];
    }

    public function getFpmExtraArgs(): array
    {
        return [];
    }
}
