<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminServiceController extends Controller
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

    public function index()
    {
        $services = Service::with('agency')
            ->orderBy('name')
            ->paginate(15);

        $allAgencies = Agency::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Admin/Service/Index', [
            'services' => $services,
            'allAgencies' => $allAgencies,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'agcy_id' => 'required|exists:agencies,id',
            'processing_days' => 'nullable|integer|min:0|max:365',
            'requirements' => 'nullable|array',
            'requirements.*.id' => 'nullable|string',
            'requirements.*.name' => 'required|string|max:255',
            'requirements.*.description' => 'nullable|string',
            'requirements.*.is_required' => 'boolean',
        ]);

        // `requirements` is a relation, not a column: the mass write below
        // must not receive it (strict-mode mass assignment would throw), so it
        // is excluded here and consumed only by syncRequirements().
        $service = Service::create(Arr::except($validated, 'requirements'));

        if ($request->has('requirements')) {
            $this->syncRequirements($service, $validated['requirements'] ?? []);
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'agcy_id' => 'required|exists:agencies,id',
            'processing_days' => 'nullable|integer|min:0|max:365',
            'requirements' => 'nullable|array',
            'requirements.*.id' => 'nullable|string',
            'requirements.*.name' => 'required|string|max:255',
            'requirements.*.description' => 'nullable|string',
            'requirements.*.is_required' => 'boolean',
        ]);

        // Validate that any provided requirement IDs belong to this service
        if ($request->has('requirements')) {
            $providedIds = collect($request->input('requirements'))->pluck('id')->filter();
            if ($providedIds->isNotEmpty()) {
                $existingIds = $service->requirements()->whereIn('id', $providedIds)->pluck('id');
                $invalidIds = $providedIds->diff($existingIds);
                if ($invalidIds->isNotEmpty()) {
                    return redirect()->back()->withErrors(['requirements' => 'Invalid requirement IDs detected.']);
                }
            }
        }

        // Same exclusion as store(): `requirements` drives syncRequirements()
        // below and must not reach the model's mass update.
        $service->update(Arr::except($validated, 'requirements'));

        if ($request->has('requirements')) {
            $this->syncRequirements($service, $validated['requirements'] ?? []);
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(string $id)
    {
        $service = Service::findOrFail($id);

        // A soft-deleted service drops out of every default-scope query that
        // renders referral services, so deleting one mid-flight strands the
        // referrals attached through the referral_services pivot: their
        // service display and requirement tracking lose their subject. Only
        // allow the delete while nothing in-flight still references it.
        $activeReferrals = DB::table('referral_services')
            ->join('referrals', 'referrals.id', '=', 'referral_services.referral_id')
            ->where('referral_services.service_id', $service->id)
            ->where('referrals.is_deleted', false)
            ->whereIn('referrals.status', self::ACTIVE_REFERRAL_STATUSES)
            ->count();

        if ($activeReferrals > 0) {
            $unit = $activeReferrals === 1 ? 'referral' : 'referrals';
            $verb = $activeReferrals === 1 ? 'uses' : 'use';

            return redirect()->route('admin.services.index')
                ->with('error', "Cannot delete {$service->name}: {$activeReferrals} active {$unit} still {$verb} this service. Complete or reject the referrals first.");
        }

        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully.');
    }

    private function syncRequirements(Service $service, array $requirements): void
    {
        $keepIds = collect($requirements)->pluck('id')->filter()->values();
        $service->requirements()->whereNotIn('id', $keepIds)->delete();
        foreach ($requirements as $req) {
            if (! empty($req['id'])) {
                $service->requirements()->where('id', $req['id'])->update([
                    'name' => $req['name'],
                    'description' => $req['description'] ?? '',
                    'is_required' => $req['is_required'] ?? false,
                ]);
            } else {
                $service->requirements()->create([
                    'name' => $req['name'],
                    'description' => $req['description'] ?? '',
                    'is_required' => $req['is_required'] ?? false,
                ]);
            }
        }
    }
}
