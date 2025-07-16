<?php

namespace staticphp\package;

use staticphp\package;
use Symfony\Component\Process\Process;

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

        // Create a process to download the latest Composer
        $process = new Process([
            'curl', '-s', '-L', 'https://github.com/composer/composer/releases/latest/download/composer.phar',
            '-o', $this->composerPath
        ]);

        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("Failed to download Composer: " . $process->getErrorOutput());
        }

        // Modify the shebang line from #!/usr/bin/php to #!/usr/bin/php-zts
        echo "Modifying shebang line in composer.phar...\n";
        $content = file_get_contents($this->composerPath);
        $content = preg_replace('|^#!/usr/bin/php|', '#!/usr/bin/php-zts', $content);
        file_put_contents($this->composerPath, $content);
        echo "Shebang line modified successfully.\n";

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
