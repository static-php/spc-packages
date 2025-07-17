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
        $debug = $input->getOption('debug');
        $phpVersion = $input->getOption('phpv');
        $target = $input->getOption('target');

        $output->writeln("Command options:");
        $output->writeln("  debug: " . ($debug ? 'true' : 'false'));
        $output->writeln("  version: {$phpVersion}");
        $output->writeln("  target: {$target}");

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
