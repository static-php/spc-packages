<?php

namespace staticphp\util;

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
    public static function renderCraftTemplate(string $phpVersion = '8.4', ?string $arch = null): string
    {
        // Detect architecture if not provided
        if ($arch === null) {
            $arch = str_contains(php_uname('m'), 'x86_64') ? 'x86_64' : 'aarch64';
        }

        // Use Twig to render the craft.yml template
        $loader = new FilesystemLoader(BASE_PATH . '/config/templates');
        $twig = new Environment($loader);

        // Prepare template variables
        $templateVars = [
            'php_version' => $phpVersion,
            'php_version_nodot' => str_replace('.', '', $phpVersion),
            'target' => SPP_TARGET,
            'arch' => $arch
        ];

        try {
            // Render the template
            return $twig->render('craft.yml.twig', $templateVars);
        } catch (\Exception $e) {
            throw new \RuntimeException("Error rendering craft.yml template: " . $e->getMessage());
        }
    }
}
