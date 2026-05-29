<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupStatusService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class BackupStatusController extends Controller
{
    public function index(BackupStatusService $service)
    {
        return Inertia::render('Admin/Backups/Index', [
            'status' => $service->getBackupStatus(),
        ]);
    }

    public function refresh(BackupStatusService $service)
    {
        Cache::forget('supabase_backups');

        return back()->with('success', 'Backup status refreshed.');
    }
}
