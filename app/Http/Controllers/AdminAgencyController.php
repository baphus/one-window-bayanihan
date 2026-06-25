<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminAgencyController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status', 'is_default']);

        $query = Agency::withCount('referrals');

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

        $perPage = min((int) ($request->per_page ?? 15), 100);
        $agencies = $query->orderBy('name')->paginate($perPage);

        return Inertia::render('Admin/Agency/Index', [
            'agencies' => $agencies,
            'filters' => $filters,
            'stats' => [
                'total' => Agency::count(),
                'active' => Agency::where('is_active', true)->count(),
                'inactive' => Agency::where('is_active', false)->count(),
                'default' => Agency::where('is_default', true)->count(),
                'total_users' => User::count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short' => 'required|string|max:50',
            'description' => 'nullable|string',
            'contact_info' => 'nullable|string',
            'logo_url' => 'nullable|image|max:2048',
            'location_query' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'map_link' => 'nullable|string|max:2048',
        ]);

        $validated['slug'] = Str::slug($validated['short']);
        $validated['is_active'] = true;

        // Handle logo upload
        if ($request->hasFile('logo_url')) {
            $path = $request->file('logo_url')->store('logos', 'public');
            $validated['logo_url'] = '/storage/'.$path;
        }

        $this->parseAndSetMapLink($validated);

        Agency::create($validated);

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $agency = Agency::findOrFail($id);

        if ($request->has('is_default') && $request->is_default === false && $agency->is_default) {
            if (Agency::where('is_default', true)->count() <= 1) {
                abort(422, 'Cannot unset is_default on the only default agency.');
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short' => 'required|string|max:50',
            'description' => 'nullable|string',
            'contact_info' => 'nullable|string',
            'logo_url' => 'nullable',
            'location_query' => 'nullable|string',
            'is_active' => 'boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'map_link' => 'nullable|string|max:2048',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo_url')) {
            $path = $request->file('logo_url')->store('logos', 'public');
            $validated['logo_url'] = '/storage/'.$path;
        } elseif ($request->has('logo_url') && $request->input('logo_url') === null) {
            // Explicitly set to null — clear existing logo
            $validated['logo_url'] = null;
        }
        // If logo_url not in request, keep existing value (not in validated)

        if (isset($validated['short']) && $validated['short'] !== $agency->short) {
            $validated['slug'] = Str::slug($validated['short']);
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

    public function show(string $id)
    {
        $agency = Agency::withCount('referrals')->findOrFail($id);
        $agency->load([
            'services',
            'services.requirements',
            'users',
            'referrals' => fn ($q) => $q->with(['caseFile.client'])->latest(),
        ]);

        return Inertia::render('Admin/Agency/Show', [
            'agency' => $agency,
        ]);
    }

    public function destroy(string $id)
    {
        $agency = Agency::findOrFail($id);

        if ($agency->is_default) {
            abort(422, 'Cannot delete the default agency.');
        }

        $agency->is_active = false;
        $agency->is_deleted = true;
        $agency->save();

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency deactivated successfully.');
    }
}
