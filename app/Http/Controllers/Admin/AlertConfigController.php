<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AlertConfigService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlertConfigController extends Controller
{
    public function index(AlertConfigService $service)
    {
        return Inertia::render('Admin/Alerts/Index', [
            'configs' => $service->getConfigs(),
            'alertLogs' => $service->getAlertLogs(),
        ]);
    }

    public function update(Request $request, AlertConfigService $service)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'enabled' => 'nullable|boolean',
            'threshold_value' => 'nullable|numeric',
            'email_recipients' => 'nullable|array',
            'email_recipients.*' => 'email',
            'notify_in_app' => 'nullable|boolean',
        ]);

        $service->update($validated['id'], $validated);

        return back()->with('success', 'Alert configuration updated.');
    }

    public function testEmail(Request $request, AlertConfigService $service)
    {
        $request->validate(['recipient' => 'required|email']);

        $result = $service->testEmail($request->recipient);
        $message = $result['success'] ? $result['message'] : 'Test failed: '.$result['message'];

        return back()->with($result['success'] ? 'success' : 'error', $message);
    }
}
