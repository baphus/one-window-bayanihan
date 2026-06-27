<?php

namespace Tests\Feature\Security;

use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StorageServiceMimeValidationTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_valid_jpeg_passes_mime_check(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $service = $this->app->make(StorageService::class);
        $errors = $service->validate($file, 'default');

        $this->assertEmpty($errors);
    }

    public function test_txt_file_renamed_to_jpg_rejected_by_mime_check(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'owb_');
        $this->tempFiles[] = $tempPath;
        file_put_contents($tempPath, 'This is plain text content pretending to be a JPEG image.');

        $file = new UploadedFile($tempPath, 'photo.jpg', null, null, true);

        $service = $this->app->make(StorageService::class);
        $errors = $service->validate($file, 'default');

        $this->assertNotEmpty($errors);
        // The MIME check must fire alongside the extension check — defense in depth
        $this->assertStringContainsString('MIME:', implode(' ', $errors));
    }

    public function test_valid_pdf_passes_mime_check(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'owb_');
        $this->tempFiles[] = $tempPath;
        // Minimal valid PDF content — enough for finfo to detect application/pdf
        $pdfContent = "%PDF-1.4\n"
            ."1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
            ."xref\n0 4\n"
            ."0000000000 65535 f \n"
            ."0000000009 00000 n \n"
            ."0000000058 00000 n \n"
            ."0000000115 00000 n \n"
            ."trailer\n<< /Size 4 /Root 1 0 R >>\n"
            ."startxref\n190\n%%EOF";
        file_put_contents($tempPath, $pdfContent);

        $file = new UploadedFile($tempPath, 'document.pdf', null, null, true);

        $service = $this->app->make(StorageService::class);
        $errors = $service->validate($file, 'default');

        $this->assertEmpty($errors);
    }
}
