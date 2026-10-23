<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceRequirement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgencyService
{
    /**
     * Referral statuses that still need a service's display name and
     * requirement tracking — PENDING/PROCESSING/FOR_COMPLIANCE are the
     * non-terminal states of the referral workflow (COMPLETED and REJECTED
     * end it), the same list the dashboards and reminder tooling use.
     *
     * @var array<int, string>
     */
    private const ACTIVE_REFERRAL_STATUSES = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];

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

            // A soft-deleted service drops out of every default-scope query
            // that renders referral services, so deleting one mid-flight
            // strands the referrals attached through the referral_services
            // pivot: their service display and requirement tracking lose
            // their subject. Refuse while anything in-flight still uses it.
            $activeReferrals = DB::table('referral_services')
                ->join('referrals', 'referrals.id', '=', 'referral_services.referral_id')
                ->where('referral_services.service_id', $service->id)
                ->where('referrals.is_deleted', false)
                ->whereIn('referrals.status', self::ACTIVE_REFERRAL_STATUSES)
                ->count();

            if ($activeReferrals > 0) {
                $unit = $activeReferrals === 1 ? 'referral' : 'referrals';
                $verb = $activeReferrals === 1 ? 'uses' : 'use';

                // Same contract as UserService::forceDelete(): the web caller
                // does not re-catch this, Inertia bounces it back with the
                // errors bag intact.
                throw ValidationException::withMessages([
                    'service' => "Cannot delete {$service->name}: {$activeReferrals} active {$unit} still {$verb} this service. Complete or reject the referrals first.",
                ]);
            }

            // Audit logging is handled by AuditObserver::deleted() — no manual log needed.

            $service->requirements()->delete();
            $service->delete();
        });
    }
}
