<?php

namespace staticphp\package;

use staticphp\package;

class embed implements package
{
    public function getFpmConfig(): array
    {
        $craftConfig = \staticphp\CraftConfig::getInstance();

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
            'files' => [
                INI_PATH . '/php.ini' => '/etc/static-php/php.ini',
                BUILD_LIB_PATH . '/libphp.so' => '/usr/static-php/libphp.so',
            ]
        ];
    }
}
