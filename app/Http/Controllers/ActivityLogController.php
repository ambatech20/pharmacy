<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->latest();
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('action')) $query->where('action', $request->action);
        if ($request->filled('model_type')) $query->where('model_type', $request->model_type);
        return view('activity-logs.index', [
            'logs' => $query->paginate(30)->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'recordTypes' => ActivityLog::query()->select('model_type')->distinct()->orderBy('model_type')->pluck('model_type'),
        ]);
    }
}
