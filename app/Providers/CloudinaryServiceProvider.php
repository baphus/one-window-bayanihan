<?php

namespace App\Providers;

use App\Cloudinary\CloudinaryUploadResult;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class CloudinaryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Cloudinary::class, function () {
            return new Cloudinary;
        });
    }

    public function boot(): void
    {
        Storage::extend('cloudinary', function ($app, $config) {
            return $app->make(Cloudinary::class);
        });

        UploadedFile::macro('storeOnCloudinary', function ($folder = null) {
            $cloudinary = app(Cloudinary::class);
            $options = [];
            if ($folder) {
                $options['folder'] = $folder;
            }
            $result = $cloudinary->uploadApi()->upload($this->getRealPath(), $options);

            return new CloudinaryUploadResult($result->getArrayCopy());
        });

        UploadedFile::macro('storeOnCloudinaryAs', function ($folder, $publicId) {
            $cloudinary = app(Cloudinary::class);
            $result = $cloudinary->uploadApi()->upload($this->getRealPath(), [
                'folder' => $folder,
                'public_id' => $publicId,
            ]);

            return new CloudinaryUploadResult($result->getArrayCopy());
        });
    }
}
