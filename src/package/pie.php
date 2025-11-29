<?php

namespace staticphp\package;

use SPC\store\CurlHook;
use SPC\store\Downloader;
use staticphp\package;
use staticphp\step\CreatePackages;

class pie implements package
{
    public function getFpmConfig(): array
    {
        [$pharSource, $wrapperSource] = $this->prepareArtifacts();

        $prefix = CreatePackages::getPrefix();

        return [
            'depends' => [
                $prefix . '-cli',
            ],
            'files' => [
                $pharSource => '/usr/share/php-zts/pie.phar',
                $wrapperSource => '/usr/bin/pie-zts',
            ],
        ];
    }

    public function getDebuginfoFpmConfig(): array
    {
        return [];
    }

    public function getFpmExtraArgs(): array
    {
        return [];
    }

    private function prepareArtifacts(): array
    {
        $pharPath = TEMP_DIR . '/pie.phar';
        if (!file_exists($pharPath)) {
            $this->downloadLatestPiePhar($pharPath);
        }

        $wrapperPath = BASE_PATH . '/src/ini/pie-zts';
        return [$pharPath, $wrapperPath];
    }

    private function downloadLatestPiePhar(string $targetPath): void
    {
        [$url, $filename] = Downloader::getLatestGithubRelease('pie', [
            'repo' => 'php/pie',
            'match' => 'pie\.phar',
            'prefer-stable' => true,
        ]);

        Downloader::downloadFile(
            name: 'pie',
            url: $url,
            filename: $filename,
            move_path: null,
            download_as: SPC_DOWNLOAD_PACKAGE,
            headers: ['Accept: application/octet-stream'],
            hooks: [[CurlHook::class, 'setupGithubToken']]
        );

        $downloaded = DOWNLOAD_PATH . '/' . $filename;
        if (!file_exists($downloaded)) {
            throw new \RuntimeException('PIE download did not produce expected file: ' . $downloaded);
        }

        if (!@copy($downloaded, $targetPath)) {
            throw new \RuntimeException('Failed to stage pie.phar to build directory.');
        }
        @chmod($targetPath, 0644);
    }
}
