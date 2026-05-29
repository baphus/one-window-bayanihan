<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Services\CloudinaryService;
use Illuminate\Console\Command;

class CheckCloudinaryUsage extends Command
{
    protected $signature = 'cloudinary:check-usage';

    protected $description = 'Check Cloudinary storage usage and log it';

    public function handle(CloudinaryService $service)
    {
        $usage = $service->getStorageUsage();
        if (isset($usage['error'])) {
            $this->error($usage['error']);

            return 1;
        }
        SystemSetting::setValue('cloudinary_storage_used_mb', (string) $usage['storage']['used_mb'], 'cloudinary', 'Cloudinary storage used in MB');
        SystemSetting::setValue('cloudinary_storage_percent', (string) $usage['storage']['usage_percent'], 'cloudinary', 'Cloudinary storage usage percentage');
        $this->info('Cloudinary usage checked: '.$usage['storage']['usage_percent'].'% used');

        return 0;
    }
}
