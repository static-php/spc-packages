<?php

namespace staticphp\util;

use staticphp\step\CreatePackages;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigRenderer
{
    /**
     * Renders a Twig template with the given variables
     *
     * @param string $phpVersion PHP version to use in the template
     * @param string|null $arch Architecture to use in the template (defaults to detected architecture)
     * @return string The rendered template content
     * @throws \RuntimeException If there's an error rendering the template
     */
    public static function renderCraftTemplate(string $phpVersion = '8.4', ?string $arch = null, ?array $packages = null): string
    {
        // Detect architecture if not provided
        if ($arch === null) {
            $arch = str_contains(php_uname('m'), 'x86_64') ? 'x86_64' : 'aarch64';
        }

        // Use Twig to render the craft.yml template
        $loader = new FilesystemLoader(BASE_PATH . '/config/templates');
        $twig = new Environment($loader);
        $majorOsVersion = trim((string) shell_exec('rpm -E %rhel 2>/dev/null')) ?: null;

        if ($majorOsVersion === null || $majorOsVersion === '') {
            // Try Ubuntu / Debian detection
            $lsb = trim((string) shell_exec('. /etc/os-release && echo $VERSION')) ?: null;
            if ($lsb !== null && $lsb !== '') {
                // Use full version string, e.g. "22.04"
                $majorOsVersion = $lsb;
            }
        }

        if ($majorOsVersion === null || $majorOsVersion === '') {
            if (str_contains(SPP_TARGET, '.2.17')) {
                $majorOsVersion = '7';
            } elseif (str_contains(SPP_TARGET, '.2.28')) {
                $majorOsVersion = '8';
            } elseif (str_contains(SPP_TARGET, '.2.34')) {
                $majorOsVersion = '9';
            } elseif (str_contains(SPP_TARGET, '.2.39')) {
                $majorOsVersion = '10';
            } else {
                $majorOsVersion = '99'; // other OS = pretend we're on el10
            }
        }

        // Prepare template variables
        $templateVars = [
            'php_version' => $phpVersion,
            'php_version_nodot' => str_replace('.', '', $phpVersion),
            'target' => SPP_TARGET,
            'arch' => $arch,
            'os' => $majorOsVersion,
            'prefix' => CreatePackages::getPrefix(),
            // Optional filter: when provided, craft.yml will include only selected packages
            // across extensions/shared-extensions/sapi, while always including cli SAPI.
            'filter_packages' => $packages,
        ];

        try {
            return $twig->render('craft.yml.twig', $templateVars);
        } catch (\Exception $e) {
            throw new \RuntimeException("Error rendering craft.yml template: " . $e->getMessage());
        }
    }
}
