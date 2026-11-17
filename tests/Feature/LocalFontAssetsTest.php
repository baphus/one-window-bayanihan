<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Tests\TestCase;

class LocalFontAssetsTest extends TestCase
{
    public function test_rendered_maintenance_page_has_no_external_scripts_or_stylesheets(): void
    {
        $this->withVite();
        $buildDirectory = 'test-font-assets-build';
        app(Vite::class)
            ->useBuildDirectory($buildDirectory)
            ->useHotFile(public_path($buildDirectory.'/hot'));
        mkdir(public_path($buildDirectory));
        file_put_contents(public_path($buildDirectory.'/manifest.json'), json_encode([
            'resources/css/app.css' => ['file' => 'assets/app-test.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
        ]));

        try {
            $html = view('errors.503', ['cspNonce' => 'test-nonce'])->render();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);

            $this->assertSame(0, $xpath->query('//script[@src]')->length);
            $links = $xpath->query('//link[@rel="stylesheet"]');
            $this->assertSame(1, $links->length);
            $this->assertSame(asset($buildDirectory.'/assets/app-test.css'), $links->item(0)->getAttribute('href'));
            $this->assertStringContainsString('nonce="test-nonce"', $html);
            $this->assertStringContainsString('under maintenance.', $html);
        } finally {
            unlink(public_path($buildDirectory.'/manifest.json'));
            rmdir(public_path($buildDirectory));
        }
    }

    public function test_app_and_maintenance_pages_use_local_styles_and_material_symbols(): void
    {
        $appView = file_get_contents(resource_path('views/app.blade.php'));
        $maintenanceView = file_get_contents(resource_path('views/errors/503.blade.php'));
        $stylesheet = file_get_contents(resource_path('css/app.css'));
        $viteConfig = file_get_contents(base_path('vite.config.js'));

        $this->assertIsString($appView);
        $this->assertIsString($maintenanceView);
        $this->assertIsString($stylesheet);
        $this->assertIsString($viteConfig);

        $this->assertStringNotContainsString('fonts.googleapis.com', $appView);
        $this->assertStringNotContainsString('fonts.gstatic.com', $appView);
        $this->assertStringNotContainsString('fonts.googleapis.com', $maintenanceView);
        $this->assertStringNotContainsString('fonts.gstatic.com', $maintenanceView);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $maintenanceView);
        $this->assertStringContainsString("@vite('resources/css/app.css')", $maintenanceView);
        $this->assertStringContainsString("'resources/css/app.css'", $viteConfig);
        $this->assertStringContainsString("url('/fonts/material-symbols-outlined.woff2')", $stylesheet);
        $this->assertMatchesRegularExpression("/font-variation-settings:\\s*'FILL' 0,\\s*'wght' 400,\\s*'GRAD' 0,\\s*'opsz' 24;/", $stylesheet);
        $this->assertFileExists(public_path('fonts/material-symbols-outlined.woff2'));
        $this->assertSame('wOF2', file_get_contents(public_path('fonts/material-symbols-outlined.woff2'), false, null, 0, 4));
        $this->assertFileExists(public_path('fonts/Material-Symbols-LICENSE.txt'));
    }
}
