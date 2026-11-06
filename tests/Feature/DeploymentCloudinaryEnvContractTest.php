<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentCloudinaryEnvContractTest extends TestCase
{
    public function test_deploy_forwards_the_cloudinary_secret_to_the_container(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

        $this->assertStringContainsString('CLOUDINARY_URL: ${{ secrets.CLOUDINARY_URL }}', $workflow);
        $this->assertStringContainsString('--arg cloudinary "$CLOUDINARY_URL"', $workflow);
        $this->assertStringContainsString('CLOUDINARY_URL: $cloudinary', $workflow);
    }
}
