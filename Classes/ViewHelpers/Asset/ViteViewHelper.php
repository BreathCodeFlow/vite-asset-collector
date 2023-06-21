<?php

declare(strict_types=1);

namespace Praetorius\ViteAssetCollector\ViewHelpers\Asset;

use Praetorius\ViteAssetCollector\Exception\ViteException;
use Praetorius\ViteAssetCollector\Service\ViteService;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * This ViewHelper adds frontend assets generated by vite to
 * TYPO3's asset collector
 */
final class ViteViewHelper extends AbstractViewHelper
{
    protected ExtensionConfiguration $extensionConfiguration;

    protected ViteService $viteService;

    /**
     * @var RenderingContext
     */
    protected $renderingContext;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'manifest',
            'string',
            'Path to vite manifest file; if omitted, default manifest from extension configuration will be used instead.'
        );
        $this->registerArgument(
            'entry',
            'string',
            'Name of entrypoint that should be included; can be omitted if manifest file exists and only contains one entrypoint'
        );
        $this->registerArgument('devTagAttributes', 'array', 'Additional attributes for dev server script tags.', false, []);
        $this->registerArgument('scriptTagAttributes', 'array', 'Additional attributes for script tags.', false, []);
        $this->registerArgument('cssTagAttributes', 'array', 'Additional attributes for css link tags.', false, []);
        $this->registerArgument(
            'priority',
            'boolean',
            'Define whether the assets should be included before other assets.',
            false,
            false
        );
    }

    public function render(): string
    {
        $assetOptions = [
            'priority' => $this->arguments['priority'],
        ];

        $manifest = $this->getManifest();

        $entry = $this->arguments['entry'];
        $entry ??= $this->viteService->determineEntrypointFromManifest($manifest);

        if ($this->useDevServer()) {
            $this->viteService->addAssetsFromDevServer(
                $this->getDevServerUri(),
                $entry,
                $assetOptions,
                $this->arguments['devTagAttributes']
            );
        } else {
            $this->viteService->addAssetsFromManifest(
                $manifest,
                $entry,
                true,
                $assetOptions,
                $this->arguments['scriptTagAttributes'],
                $this->arguments['cssTagAttributes']
            );
        }
        return '';
    }

    private function getManifest(): string
    {
        $manifest = $this->arguments['manifest'];
        $manifest ??= $this->extensionConfiguration->get('vite_asset_collector', 'defaultManifest');

        if (!is_string($manifest) || $manifest === '') {
            throw new ViteException(
                sprintf(
                    'Unable to determine vite manifest from specified argument and default manifest: %s',
                    $manifest
                ),
                1684528724
            );
        }

        return $manifest;
    }

    private function useDevServer(): bool
    {
        $useDevServer = $this->extensionConfiguration->get('vite_asset_collector', 'useDevServer');
        if ($useDevServer === 'auto') {
            return Environment::getContext()->isDevelopment();
        }
        return (bool)$useDevServer;
    }

    private function getDevServerUri(): UriInterface
    {
        $devServerUri = $this->extensionConfiguration->get('vite_asset_collector', 'devServerUri');
        if ($devServerUri === 'auto') {
            return $this->viteService->determineDevServer($this->renderingContext->getRequest());
        }
        return new Uri($devServerUri);
    }

    public function injectViteService(ViteService $viteService): void
    {
        $this->viteService = $viteService;
    }

    public function injectExtensionConfiguration(ExtensionConfiguration $extensionConfiguration): void
    {
        $this->extensionConfiguration = $extensionConfiguration;
    }
}
