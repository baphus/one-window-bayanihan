<?php

namespace App\Services;

use App\Models\AuditLog;
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
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
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
        return DB::transaction(function () use ($agencyId, $data, $userId) {
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

            AuditLog::create([
                'action' => 'CREATE',
                'module' => 'SERVICE',
                'entity_id' => $service->id,
                'new_value' => $service->toArray(),
                'user_id' => $userId,
            ]);

            return $service->load('requirements');
        });
    }

    public function updateService(string $id, string $agencyId, array $data, string $userId): Service
    {
        return DB::transaction(function () use ($id, $agencyId, $data, $userId) {
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

            AuditLog::create([
                'action' => 'UPDATE',
                'module' => 'SERVICE',
                'entity_id' => $service->id,
                'old_value' => $oldValue,
                'new_value' => $service->fresh()->toArray(),
                'user_id' => $userId,
            ]);

            return $service->fresh()->load('requirements');
        });
    }

    public function deleteService(string $id, string $agencyId, string $userId): void
    {
        DB::transaction(function () use ($id, $agencyId, $userId) {
            $service = Service::where('agcy_id', $agencyId)->findOrFail($id);

            AuditLog::create([
                'action' => 'DELETE',
                'module' => 'SERVICE',
                'entity_id' => $service->id,
                'old_value' => $service->toArray(),
                'user_id' => $userId,
            ]);

            $service->requirements()->delete();
            $service->delete();
        });
    }
}
