<?php

namespace staticphp\Command;

use staticphp\step\RunSPC;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'build',
    description: 'Build PHP with extensions using static-php-cli',
)]
class BuildCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        echo "BuildCommand::execute() called\n";

        $debug = $input->getOption('debug');
        $phpVersion = $input->getOption('phpv');
        $target = $input->getOption('target');
        $type = $input->getOption('type');

        $output->writeln("BuildCommand::execute() called");
        $output->writeln("Command options:");
        $output->writeln("  debug: " . ($debug ? 'true' : 'false'));
        $output->writeln("  version: {$phpVersion}");
        $output->writeln("  target: {$target}");
        $output->writeln("  type: {$type}");

        // Check if constants are defined
        echo "Constants check in execute():\n";
        echo "  SPP_PHP_VERSION defined: " . (defined('SPP_PHP_VERSION') ? 'yes' : 'no') . "\n";
        echo "  SPP_TARGET defined: " . (defined('SPP_TARGET') ? 'yes' : 'no') . "\n";
        echo "  BUILD_ROOT_PATH defined: " . (defined('BUILD_ROOT_PATH') ? 'yes' : 'no') . "\n";

        $output->writeln("Building PHP with extensions using static-php-cli...");
        $output->writeln("Using PHP version: {$phpVersion}");

        $result = RunSPC::run($debug, $phpVersion);

        if ($result) {
            $output->writeln("Build completed successfully.");
            $this->cleanupTempDir($output);
            return self::SUCCESS;
        }

        $output->writeln("Build failed.");
        $this->cleanupTempDir($output);
        return self::FAILURE;
    }
}
