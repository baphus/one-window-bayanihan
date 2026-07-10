<?php

namespace App\Http\Controllers;

use App\Helpers\DefaultServqualQuestions;
use App\Http\Requests\ServqualConfigStoreRequest;
use App\Http\Requests\ServqualConfigUpdateRequest;
use App\Models\Service;
use App\Models\ServqualConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgencyServqualConfigController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $configs = ServqualConfig::where('agency_id', $user->agcy_id)
            ->with('service')
            ->orderBy('created_at', 'desc')
            ->get();

        $hasMultipleConfigs = $configs->count() > 1;

        $services = Service::where('agcy_id', $user->agcy_id)
            ->where('is_deleted', false)
            ->orderBy('name')
            ->get();

        return Inertia::render('Feedback/ServqualConfig/Index', [
            'configs' => $configs,
            'hasMultipleConfigs' => $hasMultipleConfigs,
            'services' => $services,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        $defaultQuestions = array_map(fn (array $q): array => [
            'dimension' => $q['dimension'],
            'question' => $q['question'],
            'order' => $q['order'],
        ], DefaultServqualQuestions::get());

        $services = Service::where('agcy_id', $user->agcy_id)
            ->where('is_deleted', false)
            ->orderBy('name')
            ->get();

        return Inertia::render('Feedback/ServqualConfig/Form', [
            'config' => null,
            'defaultQuestions' => $defaultQuestions,
            'services' => $services,
        ]);
    }

    public function edit(string $id, Request $request): Response
    {
        $config = ServqualConfig::findOrFail($id);

        if ($config->agency_id !== $request->user()->agcy_id) {
            abort(403, 'You can only edit your own agency configs.');
        }

        $user = $request->user();

        $services = Service::where('agcy_id', $user->agcy_id)
            ->where('is_deleted', false)
            ->orderBy('name')
            ->get();

        return Inertia::render('Feedback/ServqualConfig/Form', [
            'config' => $config,
            'defaultQuestions' => DefaultServqualQuestions::get(),
            'services' => $services,
        ]);
    }

    public function store(ServqualConfigStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if (empty($validated['service_id'])) {
            $hasDefault = ServqualConfig::where('agency_id', $user->agcy_id)
                ->whereNull('service_id')
                ->exists();

            if ($hasDefault) {
                return redirect()->back()
                    ->withErrors(['service_id' => 'An agency default feedback form already exists.'])
                    ->withInput();
            }
        } else {
            $hasServiceConfig = ServqualConfig::where('agency_id', $user->agcy_id)
                ->where('service_id', $validated['service_id'])
                ->exists();

            if ($hasServiceConfig) {
                return redirect()->back()
                    ->withErrors(['service_id' => 'A feedback form is already assigned to this service.'])
                    ->withInput();
            }
        }

        $hasActiveDefault = ServqualConfig::where('agency_id', $user->agcy_id)
            ->whereNull('service_id')
            ->where('is_active', true)
            ->exists();
        $isOverride = ! empty($validated['service_id']);

        $servqualConfig = ServqualConfig::create([
            'agency_id' => $user->agcy_id,
            'name' => $validated['name'],
            'service_id' => $validated['service_id'] ?? null,
            'service_name' => $validated['service_name'],
            'questions' => $validated['questions'],
            'is_active' => $isOverride || ! $hasActiveDefault,
            'activated_at' => ($isOverride || ! $hasActiveDefault) ? now() : null,
        ]);

        return redirect()->back()->with(
            'success',
            $hasActiveDefault || $isOverride
                ? 'SERVQUAL config created successfully.'
                : 'SERVQUAL config created and set as active default.'
        );
    }

    public function update(ServqualConfigUpdateRequest $request, string $id): RedirectResponse
    {
        $config = ServqualConfig::findOrFail($id);

        if ($config->agency_id !== $request->user()->agcy_id) {
            abort(403, 'You can only modify your own agency configs.');
        }

        $validated = $request->validated();
        $targetServiceId = array_key_exists('service_id', $validated) ? $validated['service_id'] : $config->service_id;

        $duplicate = ServqualConfig::where('agency_id', $config->agency_id)
            ->where('id', '!=', $config->id)
            ->when(
                $targetServiceId === null,
                fn ($query) => $query->whereNull('service_id'),
                fn ($query) => $query->where('service_id', $targetServiceId),
            )
            ->exists();

        if ($duplicate) {
            return redirect()->back()
                ->withErrors(['service_id' => $targetServiceId === null
                    ? 'An agency default feedback form already exists.'
                    : 'A feedback form is already assigned to this service.'])
                ->withInput();
        }

        $config->update([
            'name' => $validated['name'] ?? $config->name,
            'service_id' => $targetServiceId,
            'service_name' => $validated['service_name'] ?? $config->service_name,
            'questions' => $validated['questions'] ?? $config->questions,
        ]);

        return redirect()->back()->with('success', 'SERVQUAL config updated successfully.');
    }

    public function destroy(string $id, Request $request): RedirectResponse
    {
        $config = ServqualConfig::findOrFail($id);

        if ($config->agency_id !== $request->user()->agcy_id) {
            abort(403, 'You can only delete your own agency configs.');
        }

        if ($config->is_active && $config->service_id === null) {
            $otherCount = ServqualConfig::where('agency_id', $config->agency_id)
                ->where('id', '!=', $config->id)
                ->count();

            if ($otherCount > 0) {
                return redirect()->back()->with(
                    'error',
                    'Cannot delete the active default form. Please activate another form first.'
                );
            }
        }

        $config->delete();

        return redirect()->back()->with('success', 'SERVQUAL config deleted successfully.');
    }

    public function activate(string $id, Request $request): RedirectResponse
    {
        $config = ServqualConfig::findOrFail($id);

        if ($config->agency_id !== $request->user()->agcy_id) {
            abort(403, 'You can only activate your own agency configs.');
        }

        if ($config->service_id !== null) {
            return redirect()->back()->with('info', 'Service overrides are active independently and cannot be made the agency default.');
        }

        if ($config->is_active) {
            return redirect()->back()->with('info', 'This default config is already active.');
        }

        // Deactivate any previously active default config for this agency; service overrides remain active.
        ServqualConfig::where('agency_id', $config->agency_id)
            ->whereNull('service_id')
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'activated_at' => null,
            ]);

        // Activate the selected config
        $config->update([
            'is_active' => true,
            'activated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'SERVQUAL config activated as default.');
    }

    public function assignService(string $id, Request $request): RedirectResponse
    {
        $config = ServqualConfig::findOrFail($id);

        if ($config->agency_id !== $request->user()->agcy_id) {
            abort(403, 'You can only modify your own agency configs.');
        }

        $validated = $request->validate([
            'service_id' => 'required|uuid|exists:services,id',
        ]);

        $service = Service::where('id', $validated['service_id'])
            ->where('agcy_id', $request->user()->agcy_id)
            ->where('is_deleted', false)
            ->first();

        if (! $service) {
            return redirect()->back()->withErrors(['service_id' => 'The selected service is invalid for your agency.']);
        }

        // Check no other config already assigned to this service for this agency
        $exists = ServqualConfig::where('agency_id', $config->agency_id)
            ->where('service_id', $validated['service_id'])
            ->where('id', '!=', $config->id)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'A form is already assigned to this service.');
        }

        $config->update([
            'service_id' => $validated['service_id'],
            'service_name' => $service->name,
            'is_active' => true,
            'activated_at' => $config->activated_at ?? now(),
        ]);

        return redirect()->back()->with('success', 'Form assigned to service successfully.');
    }

    public function unassignService(string $id, Request $request): RedirectResponse
    {
        $config = ServqualConfig::findOrFail($id);

        if ($config->agency_id !== $request->user()->agcy_id) {
            abort(403, 'You can only modify your own agency configs.');
        }

        if ($config->service_id === null) {
            return redirect()->back()->with('info', 'This form is already the default (not assigned to a specific service).');
        }

        // Check if a default already exists
        $hasDefault = ServqualConfig::where('agency_id', $config->agency_id)
            ->whereNull('service_id')
            ->where('id', '!=', $config->id)
            ->exists();

        if ($hasDefault) {
            $config->delete();

            return redirect()->back()->with('success', 'Service override removed. The agency default form will be used for this service.');
        }

        // No default exists — make this one the default
        $config->update([
            'service_id' => null,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Form unassigned and set as agency default.');
    }
}
