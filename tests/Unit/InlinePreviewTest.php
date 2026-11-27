<?php

namespace Tests\Unit;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InlinePreviewTest extends TestCase
{
    private function preview(string $path, string $name = 'document.pdf')
    {
        $controller = new class extends Controller
        {
            public function previewFile(string $path, string $name)
            {
                return $this->inlinePreview($path, $name);
            }
        };

        return $controller->previewFile($path, $name);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
    }

    public function test_pdf_preview_content_mime_and_security_headers(): void
    {
        Storage::disk('local')->put('pdf.bin', "%PDF-1.7\nbody");
        $response = $this->preview('pdf.bin');
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('inline; filename="document.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_png_preview_is_detected_from_content(): void
    {
        Storage::disk('local')->put('image.bin', hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c489').hex2bin('0000000b49444154789c636000020000050001a5f64540').hex2bin('0000000049454e44ae426082'));
        $response = $this->preview('image.bin');
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function test_html_named_pdf_is_rejected(): void
    {
        Storage::disk('local')->put('fake.pdf', '<!doctype html><html>not pdf</html>');
        try {
            $this->preview('fake.pdf');
            $this->fail('Unsupported content must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(415, $exception->getStatusCode());
        }
    }

    public function test_missing_preview_is_not_found(): void
    {
        try {
            $this->preview('missing.pdf');
            $this->fail('Missing content must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
