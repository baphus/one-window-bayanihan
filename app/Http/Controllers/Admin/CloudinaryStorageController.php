<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CloudinaryService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class CloudinaryStorageController extends Controller
{
    public function index(CloudinaryService $service)
    {
        return Inertia::render('Admin/Cloudinary/Index', [
            'usage' => $service->getStorageUsage(),
            'recentMedia' => $service->getRecentMedia(),
        ]);
    }

    public function refresh(CloudinaryService $service)
    {
        Cache::forget('cloudinary_usage');

        return back()->with('success', 'Cloudinary data refreshed.');
    }
}
