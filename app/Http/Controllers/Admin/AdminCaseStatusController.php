<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseStatusRequest;
use App\Models\CaseStatus;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminCaseStatusController extends Controller
{
    public function index()
    {
        $statuses = CaseStatus::orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/CaseStatus/Index', [
            'statuses' => $statuses,
        ]);
    }

    public function store(StoreCaseStatusRequest $request)
    {
        CaseStatus::create([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name'), '_')->upper(),
            'type' => $request->input('type'),
            'color' => $request->input('color'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
            'is_system' => false,
        ]);

        return redirect()->route('admin.case-statuses.index')
            ->with('success', 'Status created successfully.');
    }

    public function update(StoreCaseStatusRequest $request, string $id)
    {
        $status = CaseStatus::findOrFail($id);

        $data = [
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'color' => $request->input('color'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ];

        if (! $status->is_system) {
            $data['slug'] = Str::slug($request->input('name'), '_')->upper();
        }

        $status->update($data);

        return redirect()->route('admin.case-statuses.index')
            ->with('success', 'Status updated successfully.');
    }

    public function destroy(string $id)
    {
        $status = CaseStatus::findOrFail($id);

        if ($status->is_system) {
            return redirect()->route('admin.case-statuses.index')
                ->with('error', 'System statuses cannot be deleted.');
        }

        $status->update(['is_deleted' => true, 'is_active' => false]);

        return redirect()->route('admin.case-statuses.index')
            ->with('success', 'Status deleted successfully.');
    }
}
