<?php

namespace staticphp\Command;

use staticphp\step\RunSPC;
use staticphp\step\CreatePackages;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'all',
    description: 'Run both build and package steps',
)]
class AllCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Specify which package types to build (rpm,deb)', 'rpm,deb')
            ->addOption('packages', null, InputOption::VALUE_REQUIRED, 'Specify which packages to build (comma-separated)');    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $debug = $input->getOption('debug');
        $packageNames = $input->getOption('packages');
        $packageTypes = $input->getOption('type');
        $phpVersion = $input->getOption('phpv');

        // Run build step
        $output->writeln("Building PHP with extensions using static-php-cli...");
        $output->writeln("Using PHP version: {$phpVersion}");

        $buildResult = RunSPC::run($debug, $phpVersion);

        if (!$buildResult) {
            $output->writeln("Build failed.");
            $this->cleanupTempDir($output);
            return self::FAILURE;
        }

        // Run package step
        if ($packageNames) {
            // Split by comma to support multiple packages
            $packageNames = explode(',', $packageNames);
            $output->writeln("Creating packages for: " . implode(', ', $packageNames) . "...");
        } else {
            $output->writeln("Creating packages for all extensions...");
        }

        $packageResult = CreatePackages::run($packageNames, $packageTypes, $phpVersion);

        if (!$packageResult) {
            $output->writeln("Package creation failed.");
            $this->cleanupTempDir($output);
            return self::FAILURE;
        }

        $output->writeln("All steps completed successfully.");
        $this->cleanupTempDir($output);
        return self::SUCCESS;
    }
}
