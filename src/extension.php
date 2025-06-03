<?php

namespace staticphp;

use SPC\store\Config;

class extension implements package
{
    private string $prefix;

    public function __construct(
        private readonly string $name,
    ) {
        $this->prefix = $this->determineExtensionPrefix();
    }

    /**
     * Determine if an extension needs a prefix to ensure it's loaded after its dependencies
     *
     * @return string Prefix to use for the extension
     */
    private function determineExtensionPrefix(): string
    {
        // Get all dependencies, including transitive dependencies
        $allDependencies = $this->getExtensionDependencies($this->name);

        if (empty($allDependencies)) {
            return '';
        }

        $prefix = '';

        // Check each dependency
        foreach ($allDependencies as $dependency) {
            // If the dependency alphabetically sorts after the extension,
            // we need to add a prefix to the extension
            if (strcmp($this->name, $dependency) < 0) {
                // Add 'z' prefix to ensure it sorts after the dependency
                $prefix = 'z';

                // If the extension with 'z' prefix still sorts before the dependency,
                // add more 'z's until it sorts after
                while (strcmp($prefix . $this->name, $dependency) < 0) {
                    $prefix .= 'z';
                }
            }
        }

        return $prefix;
    }

    /**
     * Helper method to recursively get all dependencies of an extension
     *
     * @param string $extensionName Name of the extension to get dependencies for
     * @param array $visited Already visited extensions to prevent infinite recursion
     * @return array All dependencies of the extension
     */
    private function getExtensionDependencies(string $extensionName, array $visited = []): array
    {
        // Get extension configuration
        $config = Config::getExt($extensionName);
        if (!$config || empty($config['ext-depends'])) {
            return [];
        }

        $allDependencies = [];
        $visited[] = $extensionName; // Mark current extension as visited

        // Add direct dependencies
        foreach ($config['ext-depends'] as $dependency) {
            $allDependencies[] = $dependency;

            // Skip already visited dependencies to prevent infinite recursion
            if (in_array($dependency, $visited)) {
                continue;
            }

            // Get transitive dependencies using Config::getExt directly
            $depConfig = Config::getExt($dependency);
            if ($depConfig && !empty($depConfig['ext-depends'])) {
                $transitiveDeps = $this->getExtensionDependencies($dependency, $visited);

                // Add transitive shared extension dependencies
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
        $depends = ['static-php-cli'];
        foreach ($config['ext-depends'] ?? [] as $dep) {
            $depends[] = 'static-php-' . $dep;
        }

        return [
            'config-files' => [
                '/etc/static-php/php.d/'. $this->prefix . $this->name . '.ini',
            ],
            'provides' => [
                'static-php-' . $this->name,
            ],
            'depends' => $depends,
            'files' => [
                ...($this->getIniPath() ?
                    [$this->getIniPath() => '/etc/static-php/php.d/' . $this->prefix . $this->name . '.ini']
                    : []
                ),
                ...($this->isSharedExtension() ?
                    [BUILD_MODULES_PATH . '/' . $this->name . '.so' => '/usr/lib/static-php/modules/' .  $this->name . '.so']
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
        $tempIniPath = TEMP_DIR . '/' . $this->prefix . $this->name . '.ini';
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
