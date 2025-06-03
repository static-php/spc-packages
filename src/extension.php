<?php

namespace staticphp;

use SPC\store\Config;

class extension implements package
{
    public function __construct(
        private readonly string $name
    ) {

    }

    public function getFpmConfig(string $version, string $iteration): array
    {
        $config = Config::getExt($this->name);
        if (!$config) {
            throw new \Exception("Extension configuration for '{$this->name}' not found.");
        }
        $depends = ['php-cli'];
        foreach ($config['ext-depends'] ?? [] as $dep) {
            $depends[] = 'php-' . $dep;
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
                ...($this->getIniPath() ?
                    [$this->getIniPath() => '/etc/static-php/php.d/' . $this->name . '.ini']
                    : []
                ),
                ...($this->isSharedExtension() ?
                    [BUILD_MODULES_PATH . '/' . $this->name . '.so' => '/usr/lib/static-php/modules/' . $this->name . '.so']
                    : []
                ),
            ]
        ];
    }

    protected function getIniPath(): ?string
    {
        $craftConfig = CraftConfig::getInstance();
        $sharedExtensions = $craftConfig->getSharedExtensions();

        $iniPath = INI_PATH . '/extension/' . $this->name . '.ini';
        if (!file_exists($iniPath)) {
            return null;
        }

        // If this is a shared extension, create a temporary file with uncommented extension line
        if (!in_array($this->name, $sharedExtensions)) {
            return $iniPath;
        }
        $tempIniPath = TEMP_DIR . '/' . $this->name . '.ini';

        $iniContent = file_get_contents($iniPath);

        $iniContent = str_replace(';extension=' . $this->name, 'extension=' . $this->name, $iniContent);

        file_put_contents($tempIniPath, $iniContent);

        return $tempIniPath;
    }

    protected function isSharedExtension(): bool
    {
        $craftConfig = CraftConfig::getInstance();
        return in_array($this->name, $craftConfig->getSharedExtensions()) && !in_array($this->name, $craftConfig->getStaticExtensions());
    }
}
