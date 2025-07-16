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
    protected function configure(): void
    {
        $this
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Print debug messages')
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'Specify PHP version to build', '8.4')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Specify the target triple for Zig (e.g., x86_64-linux-gnu, aarch64-linux-gnu)', 'native-native');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $debug = $input->getOption('debug');
        $phpVersion = $input->getOption('version');

        $output->writeln("Building PHP with extensions using static-php-cli...");
        $output->writeln("Using PHP version: {$phpVersion}");

        $result = RunSPC::run($debug, $phpVersion);

        if ($result) {
            $output->writeln("Build completed successfully.");
            $this->cleanupTempDir($output);
            return self::SUCCESS;
        } else {
            $output->writeln("Build failed.");
            $this->cleanupTempDir($output);
            return self::FAILURE;
        }
    }
}
