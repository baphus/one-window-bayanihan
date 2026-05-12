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
        ]);

        Service::create($validated);

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
        ]);

        $service->update($validated);

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(string $id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully.');
    }
}
