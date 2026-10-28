<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that manifest.json is served with correct headers and valid PWA schema.
     */
    public function test_manifest_json_is_accessible_and_valid(): void
    {
        $response = $this->get('/manifest.json');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/manifest+json', $response->headers->get('Content-Type') ?? '');

        $manifest = json_decode($response->getContent(), true);
        $this->assertIsArray($manifest, 'Manifest content should be valid JSON.');

        // Required PWA manifest properties
        $this->assertNotEmpty($manifest['name'] ?? null);
        $this->assertNotEmpty($manifest['short_name'] ?? null);
        $this->assertEquals('/', substr($manifest['start_url'] ?? '', 0, 1));
        $this->assertEquals('/', $manifest['scope'] ?? null);
        $this->assertEquals('standalone', $manifest['display'] ?? null);
        $this->assertNotEmpty($manifest['theme_color'] ?? null);
        $this->assertNotEmpty($manifest['background_color'] ?? null);
        $this->assertIsArray($manifest['icons'] ?? null);

        // Verify icon sizes and presence
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        // Verify that maskable icons are present
        $purposes = array_column($manifest['icons'], 'purpose');
        $this->assertContains('maskable', $purposes);

        // Verify that every icon referenced in the manifest physically exists in public
        foreach ($manifest['icons'] as $icon) {
            $relativePath = ltrim(parse_url($icon['src'], PHP_URL_PATH), '/');
            $this->assertFileExists(public_path($relativePath), "Icon file {$icon['src']} must exist.");
        }
    }

    /**
     * Test that the service worker script is served with javascript MIME type and correct handlers.
     */
    public function test_service_worker_file_is_accessible_and_valid(): void
    {
        $response = $this->get('/sw.js');

        $response->assertStatus(200);
        $this->assertStringContainsString('javascript', $response->headers->get('Content-Type') ?? '');
        $this->assertEquals('/', $response->headers->get('Service-Worker-Allowed'));

        $content = $response->getContent();
        $this->assertStringContainsString('install', $content);
        $this->assertStringContainsString('activate', $content);
        $this->assertStringContainsString('fetch', $content);
        $this->assertStringContainsString('offline.html', $content);
    }

    /**
     * Test that the offline fallback page is accessible.
     */
    public function test_offline_fallback_page_is_accessible(): void
    {
        $response = $this->get('/offline');
        $response->assertStatus(200);
        $response->assertSee('Currently Offline');
        $response->assertSee('Retry Connection');

        $responseHtml = $this->get('/offline.html');
        $responseHtml->assertStatus(200);
        $responseHtml->assertSee('Currently Offline');
    }

    /**
     * Test that the main layout includes PWA manifest links, meta tags, and registration logic.
     */
    public function test_layout_includes_pwa_tags_and_service_worker(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $content = $response->getContent();

        // Manifest & iOS meta tags
        $this->assertStringContainsString('rel="manifest"', $content);
        $this->assertStringContainsString('name="theme-color"', $content);
        $this->assertStringContainsString('name="mobile-web-app-capable"', $content);
        $this->assertStringContainsString('name="apple-mobile-web-app-capable"', $content);
        $this->assertStringContainsString('rel="apple-touch-icon"', $content);

        // Service Worker registration
        $this->assertStringContainsString('navigator.serviceWorker.register', $content);

        // PWA install buttons and toast
        $this->assertStringContainsString('pwa-install-btn', $content);
        $this->assertStringContainsString('pwaInstallToast', $content);
        $this->assertStringContainsString('pwaOfflineToast', $content);
    }

    /**
     * Test that the artisan command generates all required icon assets.
     */
    public function test_pwa_generate_icons_artisan_command(): void
    {
        $this->artisan('pwa:generate-icons')
            ->expectsOutputToContain('All PWA icons generated successfully!')
            ->assertExitCode(0);

        $expectedFiles = [
            'public/manifest.json',
            'public/sw.js',
            'public/offline.html',
            'public/favicon.ico',
            'public/favicon.svg',
            'public/icons/icon.svg',
            'public/icons/icon-maskable.svg',
            'public/icons/icon-16x16.png',
            'public/icons/icon-32x32.png',
            'public/icons/icon-192x192.png',
            'public/icons/icon-512x512.png',
            'public/icons/icon-maskable-192x192.png',
            'public/icons/icon-maskable-512x512.png',
            'public/icons/apple-touch-icon.png',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists(base_path($file), "Expected PWA asset {$file} to exist.");
            $this->assertGreaterThan(0, filesize(base_path($file)), "Asset {$file} should not be empty.");
        }
    }
}
