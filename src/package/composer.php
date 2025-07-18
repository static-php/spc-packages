<?php

namespace staticphp\package;

use SPC\store\Downloader;
use staticphp\package;

class composer implements package
{
    private string $composerPath;

    public function __construct()
    {
        // Set the path where we'll download the composer.phar file
        $this->composerPath = TEMP_DIR . '/composer.phar';

        // Download the latest Composer release from GitHub
        $this->downloadLatestComposer();
    }

    /**
     * Download the latest Composer release from GitHub
     */
    private function downloadLatestComposer(): void
    {
        echo "Downloading latest Composer release...\n";

        if (!defined('DOWNLOAD_PATH')) {
            define('DOWNLOAD_PATH', TEMP_DIR);
        }
        Downloader::downloadFile(
            'composer',
            'https://github.com/composer/composer/releases/latest/download/composer.phar',
            'composer.phar',
            move_path: $this->composerPath,
            hooks: ['setupGithubToken']
        );

        // Make the file executable
        chmod($this->composerPath, 0755);

        echo "Composer downloaded successfully.\n";
    }

    public function getFpmConfig(): array
    {
        return [
            'files' => [
                $this->composerPath => '/usr/bin/composer',
            ],
            'depends' => [
                'php-zts-cli', // Composer requires PHP CLI to run
            ],
        ];
    }

    public function getFpmExtraArgs(): array
    {
        // Set the package as architecture-independent (noarch) and add metadata
        return [
            '--architecture', 'noarch',
            '--description', 'Composer is a dependency manager for PHP',
            '--url', 'https://getcomposer.org/',
            '--license', 'MIT',
            '--vendor', 'Composer',
            '--maintainer', 'Static PHP <info@static-php.dev>',
            '--category', 'Development/Tools',
        ];
    }
}
