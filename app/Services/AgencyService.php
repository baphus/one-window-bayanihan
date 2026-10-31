<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Service;
use App\Models\ServiceRequirement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgencyService
{
    public function prepareAdminData(array $data): array
    {
        $contactFields = ['contact_address' => 'Address', 'contact_tel' => 'Tel', 'contact_email' => 'Email'];
        if (array_intersect(array_keys($contactFields), array_keys($data)) === []) {
            return $data;
        }

        $lines = [];
        foreach ($contactFields as $key => $label) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }

        $contactInfo = $lines === [] ? null : implode("\n", $lines);
        if ($contactInfo !== null && mb_strlen($contactInfo) > 255) {
            throw ValidationException::withMessages(['contact_info' => 'Contact information must not exceed 255 characters.']);
        }

        $data['contact_info'] = $contactInfo;
        foreach (array_keys($contactFields) as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    public function uniqueSlug(string $short, ?string $ignoreAgencyId = null): string
    {
        $baseSlug = Str::slug($short);
        $slug = $baseSlug;
        $suffix = 2;

        while (Agency::withTrashed()->where('slug', $slug)
            ->when($ignoreAgencyId, fn ($query) => $query->where('id', '!=', $ignoreAgencyId))
            ->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

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
