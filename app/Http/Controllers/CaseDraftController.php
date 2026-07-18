<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiscardCaseDraftRequest;
use App\Http\Requests\PublishCaseDraftRequest;
use App\Http\Requests\StoreCaseDraftRequest;
use App\Http\Requests\UpdateCaseDraftRequest;
use App\Models\CaseDraft;
use App\Services\CaseDraftService;
use Illuminate\Http\JsonResponse;

final class CaseDraftController extends Controller
{
    public function __construct(private readonly CaseDraftService $service) {}

    public function store(StoreCaseDraftRequest $request): JsonResponse
    {
        $draft = $this->service->create($request->payload(), (string) $request->user()->id);

        return response()->json($this->service->response($draft), 201);
    }

    public function update(UpdateCaseDraftRequest $request, CaseDraft $draft): JsonResponse
    {
        $draft = $this->service->save(
            $draft,
            $request->payload(),
            (string) $request->user()->id,
            (int) $request->integer('expected_revision'),
        );

        return response()->json($this->service->response($draft));
    }

    public function publish(PublishCaseDraftRequest $request, CaseDraft $draft): JsonResponse
    {
        $case = $this->service->publish(
            $draft,
            (string) $request->user()->id,
            (int) $request->integer('expected_revision'),
        );

        $draft->refresh();

        return response()->json($this->service->response($draft, $case));
    }

    public function destroy(DiscardCaseDraftRequest $request, CaseDraft $draft): JsonResponse
    {
        $draft = $this->service->discard(
            $draft,
            (string) $request->user()->id,
            (int) $request->integer('expected_revision'),
        );

        return response()->json($this->service->response($draft));
    }
}
