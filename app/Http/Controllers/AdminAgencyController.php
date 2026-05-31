<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminAgencyController extends Controller
{
    public function index()
    {
        $agencies = Agency::withCount('referrals')
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('Admin/Agency/Index', [
            'agencies' => $agencies,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short' => 'required|string|max:50',
            'description' => 'nullable|string',
            'contact_info' => 'nullable|string',
            'logo_url' => 'nullable|string',
            'location_query' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['short']);
        $validated['is_active'] = true;

        Agency::create($validated);

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $agency = Agency::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short' => 'required|string|max:50',
            'description' => 'nullable|string',
            'contact_info' => 'nullable|string',
            'logo_url' => 'nullable|string',
            'location_query' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['short']) && $validated['short'] !== $agency->short) {
            $validated['slug'] = Str::slug($validated['short']);
        }

        $agency->update($validated);

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency updated successfully.');
    }

    public function show(string $id)
    {
        $agency = Agency::withCount('referrals')->findOrFail($id);
        $agency->load(['services', 'users']);

        return Inertia::render('Admin/Agency/Show', [
            'agency' => $agency,
        ]);
    }

    public function destroy(string $id)
    {
        $agency = Agency::findOrFail($id);
        $agency->is_active = false;
        $agency->is_deleted = true;
        $agency->save();

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency deactivated successfully.');
    }
}
