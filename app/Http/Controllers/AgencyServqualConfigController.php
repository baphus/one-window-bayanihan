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

        return Inertia::render('Feedback/ServqualConfig/Index', [
            'configs' => $configs,
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

        ServqualConfig::create([
            'agency_id' => $user->agcy_id,
            'service_name' => $validated['service_name'],
            'questions' => $validated['questions'],
        ]);

        return redirect()->back()->with('success', 'SERVQUAL config created successfully.');
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

        $config->delete();

        return redirect()->back()->with('success', 'SERVQUAL config deleted successfully.');
    }
}
