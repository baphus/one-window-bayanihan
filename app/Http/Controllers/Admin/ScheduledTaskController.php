<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ScheduleService;
use Inertia\Inertia;

class ScheduledTaskController extends Controller
{
    public function index(ScheduleService $service)
    {
        return Inertia::render('Admin/ScheduledTasks/Index', [
            'tasks' => $service->getTasks(),
        ]);
    }

    public function toggle(string $task, ScheduleService $service)
    {
        $service->toggle($task);

        return back()->with('success', "Task '$task' toggled.");
    }
}
