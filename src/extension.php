<?php

namespace staticphp;

use SPC\store\Config;
use staticphp\step\CreatePackages;

class extension implements package
{
    private string $prefix;

    public function __construct(
        private readonly string $name,
    )
    {
        $this->prefix = $this->determineExtensionPrefix();
    }

    private function determineExtensionPrefix(): string
    {
        if (!$this->isSharedExtension()) {
            return '';
        }

        $config = Config::getExt($this->name);

        if ($this->name === 'xdebug' || $this->name === 'ffi') {
            return '15-';
        }
        if ($config['zend-extension'] ?? false) {
            return '10-';
        }

        $allDependencies = $this->getExtensionDependencies($this->name);

        if (empty($allDependencies)) {
            return '20-';
        }

        return '30-';
    }


    public function getExtensionDependencies(string $extensionName, array $visited = []): array
    {
        $config = Config::getExt($extensionName);
        $keys = ['ext-depends', 'ext-suggests', 'ext-depends-unix', 'ext-suggests-unix', 'ext-depends-linux', 'ext-suggests-linux'];
        if (!$config) {
            return [];
        }

        $allDependencies = [];
        $visited[] = $extensionName;
        $craftConfig = CraftConfig::getInstance();

        $dependencies = [];
        foreach ($keys as $key) {
            if (isset($config[$key])) {
                foreach ($config[$key] as $item) {
                    $dependencies[] = $item;
                }
            }
        }
        foreach ($dependencies as $dependency) {
            if (!in_array($dependency, $craftConfig->getSharedExtensions()) || in_array($dependency, $craftConfig->getStaticExtensions())) {
                continue;
            }

            $allDependencies[] = $dependency;

            if (in_array($dependency, $visited)) {
                continue;
            }

            $depConfig = Config::getExt($dependency);
            if ($depConfig && !empty($depConfig['ext-depends'])) {
                $transitiveDeps = $this->getExtensionDependencies($dependency, $visited);

                foreach ($transitiveDeps as $transitiveDep) {
                    $craftConfig = CraftConfig::getInstance();
                    if (!in_array($transitiveDep, $craftConfig->getSharedExtensions()) || in_array($transitiveDep, $craftConfig->getStaticExtensions())) {
                        continue;
                    }
                    if (!in_array($transitiveDep, $allDependencies)) {
                        $allDependencies[] = $transitiveDep;
                    }
                }
            }
        }

        return $allDependencies;
    }

    public function getFpmConfig(): array
    {
        $config = Config::getExt($this->name);
        if (!$config) {
            throw new \Exception("Extension configuration for '{$this->name}' not found.");
        }
        $prefix = CreatePackages::getPrefix();
        $depends = [$prefix . '-cli'];
        $seen = [];
        $ordered = [];

        /**
         * Add a package and recursively include its ext-depends.
         *
         * @param string   $name
         * @param callable $loadConfig function(string $name): ?array
         */
        $collect = function (string $name) use (&$collect, &$ordered, &$seen, $prefix): void {
            if (isset($seen[$name])) {
                return;
            }
            $seen[$name] = true;

            $cfg = Config::getExt($name);
            if ($cfg['type'] !== 'addon') {
                $ordered[] = $prefix . '-' . $name;
            }
            if (!is_array($cfg)) {
                return;
            }

            foreach (($cfg['ext-depends'] ?? []) as $dep) {
                $collect($dep);
            }
        };

        foreach (($config['ext-depends'] ?? []) as $dep) {
            $collect($dep);
        }
        foreach (($config['ext-suggests'] ?? []) as $sug) {
            if (Config::getExt($sug)['type'] === 'addon') {
                $collect($sug);
            }
        }

        $depends = array_merge($depends, $ordered);

        return [
            'config-files' => [
                '/etc/php-zts/conf.d/' . $this->prefix . $this->name . '.ini',
            ],
            'depends' => $depends,
            'files' => [
                ...($this->getIniPath() ?
                    [$this->getIniPath() => '/etc/php-zts/conf.d/' . $this->prefix . $this->name . '.ini']
                    : []
                ),
                ...($this->isSharedExtension() ?
                    [BUILD_MODULES_PATH . '/' . $this->name . '.so' => '/usr/lib64/php-zts/modules/' . $this->name . '.so']
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

        if (!in_array($this->name, $sharedExtensions)) {
            return $iniPath;
        }
        $tempIniPath = TEMP_DIR . '/' . $this->prefix . $this->name . '.ini';
        $iniContent = file_get_contents($iniPath);
        $iniContent = str_replace(
            [';extension=' . $this->name, ';zend_extension=' . $this->name],
            ['extension=' . $this->name, 'zend_extension=' . $this->name],
            $iniContent
        );
        file_put_contents($tempIniPath, $iniContent);

        return $tempIniPath;
    }

    public function isSharedExtension(): bool
    {
        $craftConfig = CraftConfig::getInstance();
        return in_array($this->name, $craftConfig->getSharedExtensions()) && !in_array($this->name, $craftConfig->getStaticExtensions());
    }

    public function getFpmExtraArgs(): array
    {
        return [];
    }
}
