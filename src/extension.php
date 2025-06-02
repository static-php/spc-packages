<?php

namespace staticphp;

class extension implements package
{
    public function __construct(
        private string $name
    ) {

    }

    public function getFpmConfig(): array
    {
        return [
            'config-files' => [
                '/etc/static-php/php.d/'. $this->name . '.ini',
            ],
            'depends' => [
                'static-php'
            ],
            'files' => [
                BUILD_MODULES_PATH . '/' . $this->name . '.so' => '/usr/lib/static-php/modules/' . $this->name . '.so',
                INI_PATH . '/' . $this->name . '.ini' => '/etc/static-php/php.d/' . $this->name . '.ini',
            ]
        ];
    }
}
