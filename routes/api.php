<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ClientSelectController;
use App\Http\Controllers\Api\InsightsApiController;
use App\Http\Controllers\Api\PhilippineAddressController;
use Illuminate\Support\Facades\Route;

// Public address lookup endpoints (PSGC government data — no auth required)
Route::get('/address/regions', [PhilippineAddressController::class, 'regions']);
Route::get('/address/provinces', [PhilippineAddressController::class, 'provinces']);
Route::get('/address/cities', [PhilippineAddressController::class, 'cities']);
Route::get('/address/barangays', [PhilippineAddressController::class, 'barangays']);
Route::get('/address/resolve', [PhilippineAddressController::class, 'resolve']);

Route::middleware(['auth', 'verified'])->group(function () {
    // Client selection for case creation form
    Route::get('/clients', [ClientSelectController::class, 'search']);
    Route::get('/clients/{client}', [ClientSelectController::class, 'show']);
});

// Alert routes — authenticated
Route::middleware('auth')->group(function () {
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts/{id}/dismiss', [AlertController::class, 'dismiss']);
    Route::post('/alerts/{id}/read', [AlertController::class, 'read']);

    // Insights API
    Route::get('/insights/kpi-cards', [InsightsApiController::class, 'kpiCards']);
    Route::get('/insights/case-trends', [InsightsApiController::class, 'caseTrends']);
    Route::get('/insights/referral-volume', [InsightsApiController::class, 'referralVolume']);
    Route::get('/insights/sla-compliance', [InsightsApiController::class, 'slaCompliance']);
    Route::get('/insights/status-distribution', [InsightsApiController::class, 'statusDistribution']);
    Route::get('/insights/category-distribution', [InsightsApiController::class, 'categoryDistribution']);
    Route::get('/insights/service-distribution', [InsightsApiController::class, 'serviceDistribution']);
    Route::get('/insights/geographic-distribution', [InsightsApiController::class, 'geographicDistribution']);
    Route::get('/insights/client-type-split', [InsightsApiController::class, 'clientTypeSplit']);
    Route::get('/insights/aging-cases', [InsightsApiController::class, 'agingCases']);
    Route::get('/insights/stalled-referrals', [InsightsApiController::class, 'stalledReferrals']);
    Route::get('/insights/overloaded-agencies', [InsightsApiController::class, 'overloadedAgencies']);
    Route::get('/insights/bottleneck-analysis', [InsightsApiController::class, 'bottleneckAnalysis']);
    Route::get('/insights/rejection-analysis', [InsightsApiController::class, 'rejectionAnalysis']);
    Route::get('/insights/case-manager-scorecard', [InsightsApiController::class, 'caseManagerScorecard']);
    Route::get('/insights/agency-scorecard', [InsightsApiController::class, 'agencyScorecard']);
    Route::get('/insights/service-completion-rate', [InsightsApiController::class, 'serviceCompletionRate']);
    Route::get('/insights/first-response-time', [InsightsApiController::class, 'firstResponseTime']);
    Route::get('/insights/satisfaction-trend', [InsightsApiController::class, 'satisfactionTrend']);
    Route::get('/insights/servqual-scores', [InsightsApiController::class, 'servqualScores']);
    Route::get('/insights/agency-satisfaction-ranking', [InsightsApiController::class, 'agencySatisfactionRanking']);
    Route::get('/insights/feedback-volume', [InsightsApiController::class, 'feedbackVolume']);
    Route::get('/insights/case-volume-forecast', [InsightsApiController::class, 'caseVolumeForecast']);
    Route::get('/insights/breach-probability', [InsightsApiController::class, 'breachProbability']);
    Route::get('/insights/peak-periods', [InsightsApiController::class, 'peakPeriods']);
    Route::get('/insights/capacity-forecast', [InsightsApiController::class, 'capacityForecast']);
});
