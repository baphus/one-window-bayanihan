<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
            'requirements' => 'nullable|array',
            'requirements.*.id' => 'nullable|string',
            'requirements.*.name' => 'required|string|max:255',
            'requirements.*.description' => 'nullable|string',
            'requirements.*.is_required' => 'boolean',
        ]);

        $service = Service::create($validated);

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

        $service->update($validated);

        if ($request->has('requirements')) {
            $this->syncRequirements($service, $validated['requirements'] ?? []);
        }

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
