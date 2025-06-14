<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\CraftConfig;
use staticphp\step\CreatePackages;

class cli implements package
{
    public function getFpmConfig(): array
    {
        $config = CraftConfig::getInstance();
        $staticExtensions = $config->getStaticExtensions();

        $provides = ['php-zts'];
        $replaces = [];
        $configFiles = ['/etc/php-zts.ini'];
        $files = [
            INI_PATH . '/php.ini' => '/etc/php-zts.ini',
            BUILD_BIN_PATH . '/php' => '/usr/bin/php-zts',
        ];

        foreach ($staticExtensions as $ext) {
            $provides[] = CreatePackages::getPrefix() . "-{$ext}";
            $replaces[] = CreatePackages::getPrefix() . "-{$ext}";

            // Add .ini files for statically compiled extensions
            $iniFile = INI_PATH . "/extension/{$ext}.ini";
            if (file_exists($iniFile)) {
                $files[$iniFile] = "/etc/php-zts.d/{$ext}.ini";
                $configFiles[] = "/etc/php-zts.d/{$ext}.ini";
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
