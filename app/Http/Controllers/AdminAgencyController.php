<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function destroy(string $id)
    {
        $agency = Agency::findOrFail($id);
        $agency->update(['is_active' => false, 'is_deleted' => true]);

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency deactivated successfully.');
    }
}
