<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Helpers\CacheHelper;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Requests\StoreAgencyRequest;
use App\Http\Requests\UpdateAgencyRequest;
use App\Models\Agency;
use App\Services\AgencyService;
use App\Services\AuditLogger;
use App\Services\CloudinaryAvatarService;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAgencyController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status', 'is_default']);

        // withTrashed(): the agency list is the only place a deactivated agency
        // can be seen (and reactivated from) — there is no "show deleted"
        // filter on this screen — and deactivation is a real soft delete. The
        // Inactive status filter and the Reactivate action both depend on those
        // rows staying visible here.
        $query = Agency::withTrashed()->withCount('referrals');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('short', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('contact_info', 'ilike', "%{$search}%")
                    ->orWhere('location_query', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($request->has('is_default')) {
            $query->where('is_default', $request->boolean('is_default'));
        }

        $perPage = $this->perPage($request, 15);
        $agencies = $query->orderBy('name')->paginate($perPage);

        return Inertia::render('Admin/Agency/Index', [
            'agencies' => $agencies,
            'filters' => $filters,
            'stats' => CacheHelper::safeRemember('admin:agency_stats', 300, fn () => [
                'total' => Agency::count(),
                'active' => Agency::where('is_active', true)->count(),
                'inactive' => Agency::where('is_active', false)->count(),
                'default' => Agency::where('is_default', true)->count(),
            ]),
        ]);
    }

    public function store(StoreAgencyRequest $request, AgencyService $agencyService)
    {
        $validated = $agencyService->prepareAdminData($request->validated());
        $validated['slug'] = $agencyService->uniqueSlug($validated['short']);
        $validated['is_active'] = true;

        $logoFile = $request->file('logo_url');
        unset($validated['logo_url']);

        $this->parseAndSetMapLink($validated);

        $agency = Agency::create($validated);

        if ($logoFile) {
            $agency->logo_url = app(CloudinaryAvatarService::class)->uploadImage(
                $logoFile,
                'agency-logos',
                'agency-'.$agency->id,
            );
            $agency->save();
        }

        app(OnboardingService::class)
            ->markChecklistItemQuietly($request->user(), 'register-agency');

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency created successfully.');
    }

    public function update(UpdateAgencyRequest $request, string $id, AgencyService $agencyService)
    {
        // withTrashed(): a deactivated agency is still listed and its detail
        // page still edits it, so the update has to resolve the trashed row.
        $agency = Agency::withTrashed()->findOrFail($id);

        if ($request->has('is_default') && $request->is_default === false && $agency->is_default) {
            if (Agency::where('is_default', true)->count() <= 1) {
                abort(422, 'Cannot unset is_default on the only default agency.');
            }
        }

        $validated = $agencyService->prepareAdminData($request->validated());

        if ($agency->is_default && array_key_exists('is_active', $validated) && $validated['is_active'] === false) {
            abort(422, 'Default agencies cannot be deactivated.');
        }

        $logoFile = $request->file('logo_url');

        // Handle logo upload
        if ($request->hasFile('logo_url')) {
            app(CloudinaryAvatarService::class)->deleteByUrl($agency->getRawOriginal('logo_url'));
            $validated['logo_url'] = app(CloudinaryAvatarService::class)->uploadImage(
                $logoFile,
                'agency-logos',
                'agency-'.$agency->id,
            );
        } elseif ($request->has('logo_url') && $request->input('logo_url') === null) {
            // Explicitly set to null — clear existing logo
            app(CloudinaryAvatarService::class)->deleteByUrl($agency->getRawOriginal('logo_url'));
            $validated['logo_url'] = null;
        } else {
            unset($validated['logo_url']);
        }
        // If logo_url not in request, keep existing value (not in validated)

        if (isset($validated['short']) && $validated['short'] !== $agency->short) {
            $validated['slug'] = $agencyService->uniqueSlug($validated['short'], $agency->id);
        }

        $this->parseAndSetMapLink($validated);

        $agency->update($validated);

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency updated successfully.');
    }

    /**
     * Parse a Google Maps URL from map_link and extract lat/lng/location_query.
     *
     * Supports URL formats:
     *   /place/Name/@lat,lng,zoom  → lat/lng + place name as location_query
     *   ?q=lat,lng                 → lat/lng, no location_query
     *   ?q=place+name              → location_query only
     *   maps.app.goo.gl/*          → stored as-is, no parsing (lat/lng null)
     *
     * If map_link is empty/null but lat/lng are provided directly, auto-generate
     * map_link from them (backward compat with old form format).
     * If map_link is provided AND lat/lng are also provided directly, map_link wins
     * and lat/lng are overwritten from the URL parse.
     */
    private function parseAndSetMapLink(array &$data): void
    {
        // Case 1: map_link provided → parse it, overwrite lat/lng/query
        if (! empty($data['map_link'])) {
            $url = $data['map_link'];
            $parsedLat = null;
            $parsedLng = null;
            $parsedQuery = null;

            // Try @lat,lng from path-based URLs like /place/Name/@10.3,123.8,15z
            if (preg_match('/@(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $coords)) {
                $parsedLat = (float) $coords[1];
                $parsedLng = (float) $coords[2];
            }

            // Try ?q=lat,lng or ?q=place+name from query string
            $queryStr = parse_url($url, PHP_URL_QUERY);
            if ($queryStr) {
                parse_str($queryStr, $queryParams);
                if (! empty($queryParams['q'])) {
                    $qValue = $queryParams['q'];
                    // Check if q value looks like coordinates
                    if (preg_match('/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)$/', $qValue, $qCoords)) {
                        $parsedLat = (float) $qCoords[1];
                        $parsedLng = (float) $qCoords[2];
                        // Don't set location_query for coordinate-only queries
                    } elseif ($parsedLat === null) {
                        // Text query — only use if we didn't already get coords from @ pattern
                        $parsedQuery = urldecode($qValue);
                    }
                }
            }

            // Extract place name from /place/Name/ path segment (for @-style URLs)
            if ($parsedLat !== null && $parsedLng !== null) {
                $path = parse_url($url, PHP_URL_PATH);
                if ($path && preg_match('#/place/([^@]+?)(?:/|$)#', $path, $nameMatch)) {
                    $parsedQuery = trim(urldecode($nameMatch[1]), '/');
                }
            }

            $data['latitude'] = $parsedLat;
            $data['longitude'] = $parsedLng;
            $data['location_query'] = $parsedQuery;

            return;
        }

        // Case 2: no map_link, but lat/lng provided directly → auto-generate map_link (backward compat)
        if (! empty($data['latitude']) && ! empty($data['longitude'])) {
            $data['map_link'] = "https://www.google.com/maps?q={$data['latitude']},{$data['longitude']}";
        } elseif (array_key_exists('latitude', $data) || array_key_exists('longitude', $data)) {
            $data['map_link'] = null;
        }
    }

    public function show(Request $request, string $id)
    {
        // withTrashed(): the agency list surfaces deactivated agencies, and
        // their detail page still has to open.
        $agency = Agency::withTrashed()->withCount('referrals')->findOrFail($id);
        $agency->load([
            'services',
            'services.requirements',
            'users',
        ]);

        $perPage = $this->perPage($request, 15);
        $referrals = $agency->referrals()
            ->with(['caseFile.client'])
            ->latest()
            ->paginate($perPage);

        return Inertia::render('Admin/Agency/Show', [
            'agency' => $agency,
            'referrals' => $referrals,
        ]);
    }

    public function destroy(string $id)
    {
        $agency = Agency::findOrFail($id);

        if ($agency->is_default) {
            abort(422, 'Default agencies cannot be deactivated.');
        }

        $agency->is_active = false;
        $agency->delete();

        AuditLogger::log(AuditAction::DEACTIVATE)
            ->byId(auth()->id())
            ->forEntity($agency, $agency->name)
            ->module(AuditModule::AGENCY)
            ->context(['is_active' => true], ['is_active' => false])
            ->write();

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency deactivated successfully.');
    }

    public function reactivate(string $id)
    {
        $agency = Agency::withTrashed()->findOrFail($id);

        if ($agency->is_active && ! $agency->is_deleted) {
            return redirect()->route('admin.agencies.index')
                ->with('error', 'Agency is already active.');
        }

        $agency->is_active = true;
        $agency->is_deleted = false;
        $agency->deleted_at = null;
        $agency->deleted_by = null;
        $agency->saveQuietly();

        AuditLogger::log(AuditAction::REACTIVATE)
            ->byId(auth()->id())
            ->forEntity($agency, $agency->name)
            ->module(AuditModule::AGENCY)
            ->context(['is_active' => false, 'is_deleted' => true], ['is_active' => true, 'is_deleted' => false])
            ->write();

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency reactivated successfully.');
    }
}
