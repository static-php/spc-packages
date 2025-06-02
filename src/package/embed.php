<?php

namespace staticphp\package;

use staticphp\CraftConfig;
use staticphp\package;

class embed implements package
{
    public function getFpmConfig(): array
    {
        $craftConfig = CraftConfig::getInstance();

        return [
            'config-files' => [
                '/etc/static-php/php.ini',
            ],
            'provides' => [
                'static-php',
                'php-embed',
                'libphp.so',
                ...array_map(fn ($ext) => 'php-' . $ext, $craftConfig->getStaticExtensions())
            ],
            'directories' => [
                '/usr/lib/static-php',
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/etc/static-php/php.ini',
                BUILD_LIB_PATH . '/libphp.so' => '/usr/lib/static-php/libphp.so',
            ]
        ];
    }
}
