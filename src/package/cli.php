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

    public function getFpmExtraArgs(): array
    {
        $afterInstallScript = <<<'BASH'
#!/bin/bash
if [ ! -e /usr/bin/php ]; then
    ln -sf /usr/bin/php-zts /usr/bin/php
fi
BASH;
        $afterRemoveScript = <<<'BASH'
#!/bin/bash
if [ -L /usr/bin/php ] && [ "$(readlink /usr/bin/php)" = "/usr/bin/php-zts" ]; then
    rm -f /usr/bin/php
fi
BASH;

        file_put_contents(TEMP_DIR . '/cli-after-install.sh', $afterInstallScript);
        file_put_contents(TEMP_DIR . '/cli-after-remove.sh', $afterRemoveScript);
        chmod(TEMP_DIR . '/cli-after-install.sh', 0755);
        chmod(TEMP_DIR . '/cli-after-remove.sh', 0755);

        // Set the package as architecture-independent (noarch) and add metadata
        return [
            '--after-install', TEMP_DIR . '/cli-after-install.sh',
            '--after-remove', TEMP_DIR . '/cli-after-remove.sh'
        ];
    }
}
