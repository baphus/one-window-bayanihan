<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHelpdeskTagRequest;
use App\Services\HelpdeskService;
use Inertia\Inertia;

class HelpdeskTagController extends Controller
{
    public function __construct(
        private readonly HelpdeskService $helpdeskService,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/Helpdesk/Tags', [
            'tags' => $this->helpdeskService->getTags(),
        ]);
    }

    public function store(StoreHelpdeskTagRequest $request)
    {
        $tag = $this->helpdeskService->createTag($request->validated());

        if ($request->input('_quick')) {
            return response()->json([
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'articles_count' => 0,
            ]);
        }

        return redirect()
            ->route('admin.helpdesk.tags.index')
            ->with('success', 'Tag created successfully.');
    }

    public function update(StoreHelpdeskTagRequest $request, string $id)
    {
        $this->helpdeskService->updateTag($id, $request->validated());

        return redirect()
            ->route('admin.helpdesk.tags.index')
            ->with('success', 'Tag updated successfully.');
    }

    public function destroy(string $id)
    {
        $this->helpdeskService->deleteTag($id);

        return redirect()
            ->route('admin.helpdesk.tags.index')
            ->with('success', 'Tag deleted successfully.');
    }
}
