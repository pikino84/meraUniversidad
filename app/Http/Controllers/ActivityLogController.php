<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * Historial de cambios (solo Super Admin).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = Activity::query()
            ->with('causer')
            ->when($request->filled('log'), fn ($q) => $q->where('log_name', $request->input('log')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $logNames = Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter();

        return view('activity_logs.index', compact('logs', 'logNames'));
    }
}
