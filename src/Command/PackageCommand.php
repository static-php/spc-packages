<?php

namespace staticphp\Command;

use staticphp\step\CreatePackages;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'package',
    description: 'Create packages for all extensions or specific packages',
)]
class PackageCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('packages', null, InputOption::VALUE_REQUIRED, 'Specify which packages to build (comma-separated)')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Specify which package types to build (rpm,deb)', 'rpm,deb')
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'Specify PHP version to build', '8.4')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Specify the target triple for Zig (e.g., x86_64-linux-gnu, aarch64-linux-gnu)', 'native-native');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageNames = $input->getOption('packages');
        $packageTypes = $input->getOption('type');
        $phpVersion = $input->getOption('version');

        if ($packageNames) {
            // Split by comma to support multiple packages
            $packageNames = explode(',', $packageNames);
            $output->writeln("Creating packages for: " . implode(', ', $packageNames) . "...");
        } else {
            $output->writeln("Creating packages for all extensions...");
        }

        $result = CreatePackages::run($packageNames, $packageTypes, $phpVersion);

        if ($result) {
            $output->writeln("Package creation completed successfully.");
            $this->cleanupTempDir($output);
            return self::SUCCESS;
        } else {
            $output->writeln("Package creation failed.");
            $this->cleanupTempDir($output);
            return self::FAILURE;
        }
    }
}
