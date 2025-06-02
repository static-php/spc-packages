<?php

namespace staticphp;

use SPC\store\Config;

class extension implements package
{
    public function __construct(
        private readonly string $name
    ) {

    }

    public function getFpmConfig(): array
    {
        $config = Config::getExt($this->name);
        if (!$config) {
            throw new \Exception("Extension configuration for '{$this->name}' not found.");
        }
        $depends = ['static-php'];
        foreach ($config['ext-depends'] ?? [] as $dep) {
            $depends[] = 'php-' . $dep;
        }

        // Get the original ini file path
        $originalIniPath = INI_PATH . '/extension/' . $this->name . '.ini';
        $iniPath = $originalIniPath;

        // Check if this is a shared extension
        $craftConfig = CraftConfig::getInstance();
        $sharedExtensions = $craftConfig->getSharedExtensions();

        // If this is a shared extension, create a temporary file with uncommented extension line
        if (in_array($this->name, $sharedExtensions)) {
            // Create a temporary file
            $tempIniPath = TEMP_DIR . '/' . $this->name . '.ini';

            // Read the original ini file
            $iniContent = file_get_contents($originalIniPath);

            // Replace the commented extension line with uncommented one
            $iniContent = preg_replace('/^;extension=' . $this->name . '$/m', 'extension=' . $this->name, $iniContent);

            // Write to the temporary file
            file_put_contents($tempIniPath, $iniContent);

            // Use the temporary file instead of the original
            $iniPath = $tempIniPath;
        }

        return [
            'config-files' => [
                '/etc/static-php/php.d/'. $this->name . '.ini',
            ],
            'provides' => [
                'php-' . $this->name,
            ],
            'depends' => $depends,
            'files' => [
                BUILD_MODULES_PATH . '/' . $this->name . '.so' => '/usr/lib/static-php/modules/' . $this->name . '.so',
                $iniPath => '/etc/static-php/php.d/' . $this->name . '.ini',
            ]
        ];
    }
}
