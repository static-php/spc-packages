<?php

namespace staticphp;

use Symfony\Component\Yaml\Yaml;

class CraftConfig
{
    private static $instance = null;
    private $craftConfig;
    private $extensions = [];
    private $sharedExtensions = [];
    private $sapis = [];

    private function __construct()
    {
        $this->loadConfig();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig()
    {
        $craftYmlPath = BASE_PATH . '/config/craft.yml';

        if (!file_exists($craftYmlPath)) {
            throw new \RuntimeException("Configuration file not found: {$craftYmlPath}");
        }

        $this->craftConfig = Yaml::parseFile($craftYmlPath);

        // Get the list of extensions
        if (isset($this->craftConfig['extensions'])) {
            $extensions = $this->craftConfig['extensions'];
            if (is_string($extensions)) {
                $extensions = explode(',', $extensions);
            }
            $this->extensions = array_map('trim', $extensions);
        }

        // Get the list of shared extensions
        if (isset($this->craftConfig['shared-extensions'])) {
            $sharedExtensions = $this->craftConfig['shared-extensions'];
            if (is_string($sharedExtensions)) {
                $sharedExtensions = explode(',', $sharedExtensions);
            }
            $this->sharedExtensions = array_map('trim', $sharedExtensions);
        }

        // Get the list of SAPIs
        if (isset($this->craftConfig['sapi'])) {
            $sapis = $this->craftConfig['sapi'];
            if (is_string($sapis)) {
                $sapis = explode(',', $sapis);
            }
            $this->sapis = array_map('trim', $sapis);
        }
    }

    /**
     * Get the list of static extensions from craft.yml
     *
     * @return array List of static extensions
     */
    public function getStaticExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Get the list of shared extensions from craft.yml
     *
     * @return array List of shared extensions
     */
    public function getSharedExtensions(): array
    {
        return $this->sharedExtensions;
    }

    /**
     * Get the list of SAPIs from craft.yml
     *
     * @return array List of SAPIs
     */
    public function getSapis(): array
    {
        return $this->sapis;
    }

    /**
     * Get the raw craft.yml configuration
     *
     * @return array Raw configuration
     */
    public function getRawConfig(): array
    {
        return $this->craftConfig;
    }
}
