<?php

namespace App\Http\Controllers;

use App\Helpers\DefaultServqualQuestions;
use App\Http\Requests\ServqualConfigStoreRequest;
use App\Http\Requests\ServqualConfigUpdateRequest;
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
            ->orderBy('created_at', 'desc')
            ->get();

        $hasMultipleConfigs = $configs->count() > 1;

        return Inertia::render('Feedback/ServqualConfig/Index', [
            'configs' => $configs,
            'hasMultipleConfigs' => $hasMultipleConfigs,
        ]);
    }

    public function create(): Response
    {
        $defaultQuestions = array_map(fn (array $q): array => [
            'dimension' => $q['dimension'],
            'question' => $q['question'],
            'order' => $q['order'],
        ], DefaultServqualQuestions::get());

        return Inertia::render('Feedback/ServqualConfig/Form', [
            'config' => null,
            'defaultQuestions' => $defaultQuestions,
        ]);
    }

    public function edit(string $id, Request $request): Response
    {
        $config = ServqualConfig::findOrFail($id);

        if ($config->agency_id !== $request->user()->agcy_id) {
            abort(403, 'You can only edit your own agency configs.');
        }

        return Inertia::render('Feedback/ServqualConfig/Form', [
            'config' => $config,
            'defaultQuestions' => DefaultServqualQuestions::get(),
        ]);
    }

    public function store(ServqualConfigStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $hasActive = ServqualConfig::where('agency_id', $user->agcy_id)
            ->where('is_active', true)
            ->exists();

        $servqualConfig = ServqualConfig::create([
            'agency_id' => $user->agcy_id,
            'service_name' => $validated['service_name'],
            'questions' => $validated['questions'],
            'is_active' => ! $hasActive,
            'activated_at' => $hasActive ? null : now(),
        ]);

        return redirect()->back()->with(
            'success',
            $hasActive
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

        $config->update([
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

        if ($config->is_active) {
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

        if ($config->is_active) {
            return redirect()->back()->with('info', 'This config is already the active default.');
        }

        // Deactivate any previously active config for this agency
        ServqualConfig::where('agency_id', $config->agency_id)
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
}
