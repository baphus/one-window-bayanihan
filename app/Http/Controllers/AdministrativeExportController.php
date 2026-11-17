<?php

namespace App\Http\Controllers;

use App\Enums\AuditModule;
use App\Http\Requests\AdministrativeExportRequest;
use App\Services\AuditLogger;
use App\Services\Reports\AdministrativeExportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdministrativeExportController extends Controller
{
    public function __construct(private readonly AdministrativeExportService $exports) {}

    public function __invoke(AdministrativeExportRequest $request): BinaryFileResponse|JsonResponse
    {
        $scope = $request->validated();
        try {
            $this->audit($request, 'REQUESTED', $scope, []);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Export audit is unavailable; no workbook was generated.'], 503);
        }
        $result = null;
        try {
            $result = $this->exports->generate($scope);
            if ($result['path'] === null) {
                $this->audit($request, 'REJECTED', $scope, $result['counts'] + ['reason' => $result['reason']]);

                return response()->json(['message' => $result['message'], 'counts' => $result['counts']], $result['status']);
            }

            $this->audit($request, 'GENERATED', $scope, $result['counts']);
            $response = response()->download($result['path'], $result['filename'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]);
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');

            return $response->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (($result['path'] ?? null) !== null && is_file($result['path'])) {
                @unlink($result['path']);
            }
            try {
                $this->audit($request, 'FAILED', $scope, $result['counts'] ?? []);
            } catch (\Throwable $auditFailure) {
                report($auditFailure);

                return response()->json(['message' => 'Export audit is unavailable; no workbook was generated.'], 503);
            }
            report($e);

            return response()->json(['message' => 'Export generation failed. Please try again.'], 500);
        }
    }

    private function audit(AdministrativeExportRequest $request, string $outcome, array $scope, array $details): void
    {
        AuditLogger::export()->by($request->user())->module(AuditModule::DATA_EXPORT)
            ->description('Administrative export '.$outcome.'.')
            ->context(null, ['report' => 'administrative', 'correlation_id' => $request->correlationId(), 'outcome' => $outcome, 'scope' => $scope, ...$details])
            ->write();
    }
}
