<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\CraftConfig;

class cli implements package
{
    public function getFpmConfig(): array
    {
        $config = CraftConfig::getInstance();
        $staticExtensions = $config->getStaticExtensions();

        $provides = ['php'];
        $replaces = [];
        $configFiles = ['/etc/static-php/php.ini'];
        $files = [
            INI_PATH . '/php.ini' => '/etc/static-php/php.ini',
            BUILD_BIN_PATH . '/php' => '/usr/static-php/bin/php',
            INI_PATH . '/static-php.sh' => '/etc/profile.d/static-php.sh',
        ];

        foreach ($staticExtensions as $ext) {
            $provides[] = "static-php-{$ext}";
            $replaces[] = "static-php-{$ext}";

            // Add .ini files for statically compiled extensions
            $iniFile = INI_PATH . "/extension/{$ext}.ini";
            if (file_exists($iniFile)) {
                $files[$iniFile] = "/etc/static-php/php.d/{$ext}.ini";
                $configFiles[] = "/etc/static-php/php.d/{$ext}.ini";
            }
        }

        return [
            'config_files' => $configFiles,
            'provides' => $provides,
            'replaces' => $replaces,
            'files' => $files
        ];
    }
}
