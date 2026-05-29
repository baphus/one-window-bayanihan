<?php

return [
    'cloud_url' => env('CLOUDINARY_URL') ?: (env('CLOUDINARY_CLOUD_NAME') && env('CLOUDINARY_API_KEY') && env('CLOUDINARY_API_SECRET')
        ? sprintf('cloudinary://%s:%s@%s', env('CLOUDINARY_API_KEY'), env('CLOUDINARY_API_SECRET'), env('CLOUDINARY_CLOUD_NAME'))
        : null),
];
