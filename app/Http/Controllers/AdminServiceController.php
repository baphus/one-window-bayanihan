<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Agency;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminServiceController extends Controller
{
    public function index()
    {
        $services = Service::with('agencies')
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
            'agency_ids' => 'nullable|array',
            'agency_ids.*' => 'exists:agencies,id',
        ]);

        $service = Service::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['agency_ids'])) {
            $service->agencies()->attach($validated['agency_ids']);
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
            'agency_ids' => 'nullable|array',
            'agency_ids.*' => 'exists:agencies,id',
        ]);

        $service->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (isset($validated['agency_ids'])) {
            $service->agencies()->sync($validated['agency_ids']);
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(string $id)
    {
        $service = Service::findOrFail($id);
        $service->agencies()->detach();
        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully.');
    }
}
