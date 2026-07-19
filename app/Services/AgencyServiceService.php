<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceRequirement;
use Illuminate\Support\Facades\DB;

class AgencyServiceService
{
    public function getServices(string $agencyId, array $filters = [])
    {
        $query = Service::with('requirements')
            ->where('agcy_id', $agencyId)
            ->orderBy('created_at', 'desc');

        $search = $filters['search'] ?? null;

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        return $query->paginate(20);
    }

    public function allServices(string $agencyId): array
    {
        return Service::with('requirements')
            ->where('agcy_id', $agencyId)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function createService(string $agencyId, array $data, string $userId): Service
    {
        return DB::transaction(function () use ($agencyId, $data) {
            $service = Service::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'agcy_id' => $agencyId,
            ]);

            $requirements = $data['requirements'] ?? [];

            foreach ($requirements as $reqName) {
                ServiceRequirement::create([
                    'name' => $reqName,
                    'service_id' => $service->id,
                    'is_required' => true,
                ]);
            }

            // Audit logging is handled by AuditObserver::created() — no manual log needed.

            return $service->load('requirements');
        });
    }

    public function updateService(string $id, string $agencyId, array $data, string $userId): Service
    {
        return DB::transaction(function () use ($id, $agencyId, $data) {
            $service = Service::where('agcy_id', $agencyId)->findOrFail($id);
            $oldValue = $service->toArray();

            $service->update([
                'name' => $data['name'] ?? $service->name,
                'description' => $data['description'] ?? $service->description,
            ]);

            $requirements = $data['requirements'] ?? null;

            if ($requirements !== null) {
                ServiceRequirement::where('service_id', $service->id)->delete();

                foreach ($requirements as $reqName) {
                    ServiceRequirement::create([
                        'name' => $reqName,
                        'service_id' => $service->id,
                        'is_required' => true,
                    ]);
                }
            }

            // Audit logging is handled by AuditObserver::updated() — no manual log needed.

            return $service->fresh()->load('requirements');
        });
    }

    public function deleteService(string $id, string $agencyId, string $userId): void
    {
        DB::transaction(function () use ($id, $agencyId) {
            $service = Service::where('agcy_id', $agencyId)->findOrFail($id);

            // Audit logging is handled by AuditObserver::deleted() — no manual log needed.

            $service->requirements()->delete();
            $service->delete();
        });
    }
}
