<?php

namespace Tests\Feature\Security;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FileExtensionValidationTest extends TestCase
{
    public function test_guess_extension_uses_content_not_client_filename(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'owb_');
        $filePath = $tempPath.'.gif';
        rename($tempPath, $filePath);

        // Write minimal valid GIF89a binary content
        file_put_contents($filePath, "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xf9\x04\x00\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b");

        // Client claims the file is attack.jpg, but content is actually GIF
        $file = new UploadedFile($filePath, 'attack.jpg', null, null, true);

        // getClientOriginalExtension() trusts the client-supplied filename
        $this->assertSame('jpg', $file->getClientOriginalExtension());

        // guessExtension() inspects the actual file content via MIME detection
        $this->assertSame('gif', $file->guessExtension());

        // These must differ — that's the security boundary
        $this->assertNotSame(
            $file->getClientOriginalExtension(),
            $file->guessExtension()
        );

        unlink($filePath);
    }
}
