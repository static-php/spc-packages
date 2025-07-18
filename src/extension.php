<?php

namespace staticphp;

use SPC\store\Config;
use staticphp\step\CreatePackages;

class extension implements package
{
    private string $prefix;
    private array $dependencies;

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

        $allDependencies = $this->getExtensionDependencies($this->name);

        if (empty($allDependencies)) {
            return '';
        }

        $prefix = '';

        foreach ($allDependencies as $dependency) {
            if (strcmp($this->name, $dependency) < 0) {
                $prefix = 'z';

                while (strcmp($prefix . $this->name, $dependency) < 0) {
                    $prefix .= 'z';
                }
            }
        }

        return $prefix;
    }

    public function getExtensionDependencies(string $extensionName, array $visited = []): array
    {
        $config = Config::getExt($extensionName);
        if (!$config || empty($config['ext-depends'])) {
            return [];
        }

        $allDependencies = [];
        $visited[] = $extensionName;
        $craftConfig = CraftConfig::getInstance();

        foreach ($config['ext-depends'] as $dependency) {
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
        $depends = [CreatePackages::getPrefix() . '-cli'];
        foreach ($config['ext-depends'] ?? [] as $dep) {
            $depends[] = CreatePackages::getPrefix() . '-' . $dep;
        }

        return [
            'config-files' => [
                '/etc/php-zts.d/' . $this->prefix . $this->name . '.ini',
            ],
            'depends' => $depends,
            'files' => [
                ...($this->getIniPath() ?
                    [$this->getIniPath() => '/etc/php-zts.d/' . $this->prefix . $this->name . '.ini']
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
