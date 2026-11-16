<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ScheduleHealth;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(ScheduleHealth $scheduleHealth): View
    {
        $jobs = $scheduleHealth->report();

        return view('admin.dashboard', [
            'userCount' => User::query()->count(),
            'scheduledJobs' => $jobs,
            'statsAreDelayed' => collect($jobs)->firstWhere('command', ScheduleHealth::STATS_JOB)['is_overdue'],
        ]);
    }
}
