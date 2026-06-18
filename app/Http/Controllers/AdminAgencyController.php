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
        ]);

        $validated['slug'] = Str::slug($validated['short']);
        $validated['is_active'] = true;

        // Handle logo upload
        if ($request->hasFile('logo_url')) {
            $path = $request->file('logo_url')->store('logos', 'public');
            $validated['logo_url'] = '/storage/'.$path;
        }

        // Auto-generate map_link from lat/lng
        if (! empty($validated['latitude']) && ! empty($validated['longitude'])) {
            $validated['map_link'] = "https://www.google.com/maps?q={$validated['latitude']},{$validated['longitude']}";
        } elseif (array_key_exists('latitude', $validated) || array_key_exists('longitude', $validated)) {
            $validated['map_link'] = null;
        }

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

        // Auto-generate map_link from lat/lng
        if (! empty($validated['latitude']) && ! empty($validated['longitude'])) {
            $validated['map_link'] = "https://www.google.com/maps?q={$validated['latitude']},{$validated['longitude']}";
        } elseif (array_key_exists('latitude', $validated) || array_key_exists('longitude', $validated)) {
            $validated['map_link'] = null;
        }

        $agency->update($validated);

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency updated successfully.');
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
