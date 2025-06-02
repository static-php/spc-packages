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
                INI_PATH . '/extension/' . $this->name . '.ini' => '/etc/static-php/php.d/' . $this->name . '.ini',
            ]
        ];
    }
}
