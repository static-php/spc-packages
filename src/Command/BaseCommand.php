<?php

namespace staticphp\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Print debug messages')
            ->addOption('phpv', null, InputOption::VALUE_REQUIRED, 'Specify PHP version to build', '8.4')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Specify the target triple for Zig (e.g., x86_64-linux-gnu, aarch64-linux-gnu)', 'native-native');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // Define build paths with PHP version
        $phpVersion = $input->getOption('phpv') ?? '8.4';
        $target = $input->getOption('target') ?? 'native-native';

        // Check if constants are already defined
        if (defined('SPP_PHP_VERSION')) {
            echo "Constants already defined. SPP_PHP_VERSION=" . SPP_PHP_VERSION . "\n";
            return;
        }

        // Define constants
        define('SPP_PHP_VERSION', $phpVersion);
        define('SPP_TARGET', $target);
        define('BUILD_ROOT_PATH', BASE_PATH . '/build/' . $phpVersion);
        define('BUILD_BIN_PATH', BUILD_ROOT_PATH . '/bin');
        define('BUILD_LIB_PATH', BUILD_ROOT_PATH . '/lib');
        define('BUILD_INCLUDE_PATH', BUILD_ROOT_PATH . '/include');
        define('BUILD_MODULES_PATH', BUILD_ROOT_PATH . '/modules');

        // Create necessary directories
        $this->createDirectories();
    }

    protected function createDirectories(): void
    {
        $paths = [BUILD_ROOT_PATH, BUILD_BIN_PATH, BUILD_LIB_PATH, BUILD_MODULES_PATH, DIST_PATH, DIST_RPM_PATH, DIST_DEB_PATH];
        foreach ($paths as $path) {
            if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
                throw new \RuntimeException("Failed to create directory: " . $path);
            }
        }

        // Create temporary directory
        if (file_exists(TEMP_DIR)) {
            // Clean up any existing files
            exec('rm -rf ' . escapeshellarg(TEMP_DIR . '/*'));
        } elseif (!mkdir(TEMP_DIR, 0755, true) && !is_dir(TEMP_DIR)) {
            throw new \RuntimeException("Failed to create directory: " . TEMP_DIR);
        }
    }

    protected function cleanupTempDir(OutputInterface $output): void
    {
        if (file_exists(TEMP_DIR)) {
            $output->writeln("Cleaning up temporary directory...");
            exec('rm -rf ' . escapeshellarg(TEMP_DIR));
        }
    }
}
