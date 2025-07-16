<?php

namespace staticphp\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // Define build paths with PHP version
        $phpVersion = $input->getOption('version') ?? '8.4';
        $target = $input->getOption('target') ?? 'native-native';

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
        if (!mkdir(BUILD_ROOT_PATH, 0755, true) && !is_dir(BUILD_ROOT_PATH)) {
            throw new \RuntimeException("Failed to create directory: " . BUILD_ROOT_PATH);
        }
        if (!mkdir(BUILD_BIN_PATH, 0755, true) && !is_dir(BUILD_BIN_PATH)) {
            throw new \RuntimeException("Failed to create directory: " . BUILD_BIN_PATH);
        }
        if (!mkdir(BUILD_MODULES_PATH, 0755, true) && !is_dir(BUILD_MODULES_PATH)) {
            throw new \RuntimeException("Failed to create directory: " . BUILD_MODULES_PATH);
        }
        if (!mkdir(BUILD_LIB_PATH, 0755, true) && !is_dir(BUILD_LIB_PATH)) {
            throw new \RuntimeException("Failed to create directory: " . BUILD_LIB_PATH);
        }
        if (!mkdir(DIST_PATH, 0755, true) && !is_dir(DIST_PATH)) {
            throw new \RuntimeException("Failed to create directory: " . DIST_PATH);
        }
        if (!mkdir(DIST_RPM_PATH, 0755, true) && !is_dir(DIST_RPM_PATH)) {
            throw new \RuntimeException("Failed to create directory: " . DIST_RPM_PATH);
        }
        if (!mkdir(DIST_DEB_PATH, 0755, true) && !is_dir(DIST_DEB_PATH)) {
            throw new \RuntimeException("Failed to create directory: " . DIST_DEB_PATH);
        }

        // Create temporary directory
        if (file_exists(TEMP_DIR)) {
            // Clean up any existing files
            exec('rm -rf ' . escapeshellarg(TEMP_DIR . '/*'));
        } else {
            if (!mkdir(TEMP_DIR, 0755, true) && !is_dir(TEMP_DIR)) {
                throw new \RuntimeException("Failed to create directory: " . TEMP_DIR);
            }
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
