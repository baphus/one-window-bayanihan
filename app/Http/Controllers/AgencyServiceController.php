<?php

namespace App\Http\Controllers;

use App\Services\AgencyServiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgencyServiceController extends Controller
{
    public function __construct(
        private readonly AgencyServiceService $serviceService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $services = $this->serviceService->getServices(
            $user->agcy_id,
            $request->only(['search']),
        );

        $allServices = $this->serviceService->allServices($user->agcy_id);

        return Inertia::render('Agency/Services/Index', [
            'services' => $services,
            'allServices' => $allServices,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'requirements' => 'nullable|array',
            'requirements.*' => 'string|max:255',
        ]);

        $service = $this->serviceService->createService(
            $request->user()->agcy_id,
            $validated,
            $request->user()->id,
        );

        return redirect()
            ->route('agency.services.index')
            ->with('success', 'Service created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'requirements' => 'nullable|array',
            'requirements.*' => 'string|max:255',
        ]);

        $service = $this->serviceService->updateService(
            $id,
            $request->user()->agcy_id,
            $validated,
            $request->user()->id,
        );

        return redirect()
            ->route('agency.services.index')
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $this->serviceService->deleteService(
            $id,
            $request->user()->agcy_id,
            $request->user()->id,
        );

        return redirect()
            ->route('agency.services.index')
            ->with('success', 'Service deleted successfully.');
    }
}
