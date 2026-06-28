<?php

namespace App\Services;

use App\Models\CaseDocument;
use App\Models\ReferralAttachment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseDashboardService
{
    public function getDashboard(): array
    {
        return Cache::remember('supabase_dashboard', 300, function () {
            return [
                'project' => $this->getProjectStatus(),
                'storage' => [
                    'buckets' => [
                        ['name' => 'case-documents', 'visibility' => 'private'],
                        ['name' => 'referrals', 'visibility' => 'private'],
                        ['name' => 'helpdesk-images', 'visibility' => 'public'],
                    ],
                    'file_counts' => [
                        'case_documents' => CaseDocument::where('is_deleted', false)->count(),
                        'referral_attachments' => ReferralAttachment::where('is_deleted', false)->count(),
                    ],
                ],
                'backups' => $this->getBackupSnapshot(),
            ];
        });
    }

    private function getProjectStatus(): array
    {
        try {
            $ref = $this->getProjectRef();
            $response = Http::withToken(config('services.supabase.service_key'))
                ->timeout(5)
                ->get("https://api.supabase.com/v1/projects/{$ref}");

            if (! $response->successful()) {
                return ['status' => 'unreachable', 'error' => "HTTP {$response->status()}"];
            }

            $data = $response->json();

            return [
                'status' => $data['status'] ?? 'unknown',
                'name' => $data['name'] ?? '',
                'region' => $data['region'] ?? '',
                'created_at' => $data['created_at'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Supabase dashboard data', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function getBackupSnapshot(): array
    {
        try {
            $ref = $this->getProjectRef();
            $response = Http::withToken(config('services.supabase.service_key'))
                ->timeout(5)
                ->get("https://api.supabase.com/v1/projects/{$ref}/database/backups");

            if (! $response->successful()) {
                return ['error' => 'Could not fetch backups'];
            }

            $data = $response->json();
            $backups = collect($data['backups'] ?? []);
            $completed = $backups->where('status', 'completed');

            return [
                'last_backup' => $completed->first()['inserted_at'] ?? 'No completed backups',
                'total_backups' => $backups->count(),
                'completed_backups' => $completed->count(),
                'total_size_mb' => round($backups->sum('size_bytes') / 1048576, 2),
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Supabase backup data', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    private function getProjectRef(): string
    {
        $url = config('services.supabase.url', '');

        if (! $url) {
            return '';
        }

        preg_match('/https:\/\/(.+)\.supabase\.co/', $url, $matches);

        return $matches[1] ?? $url;
    }
}
