<?php

namespace staticphp;

class extension implements package
{
    private function name(): string
    {
        return class_basename(self::class);
    }

    public function getFpmConfig(): array
    {
        return [
            'config-files' => [
                '/etc/static-php/php.d/'. $this->name() . '.ini',
            ],
            'depends' => [
                'static-php'
            ],
            'files' => [
                BUILD_MODULES_PATH . '/' . $this->name() . '.so' => '/usr/lib/static-php/modules/' . $this->name() . '.so',
                __DIR__ . '/' . $this->name() . '.ini' => '/etc/static-php/php.d/' . $this->name() . '.ini',
            ]
        ];
    }
}
