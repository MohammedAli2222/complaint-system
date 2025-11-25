<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $logs = Log::with('user')
            ->when($request->action, fn($q, $action) => $q->where('action', $action))
            ->when($request->date, fn($q, $date) => $q->whereDate('timestamp', $date))
            ->orderByDesc('timestamp')
            ->paginate(50);

        return response()->json($logs);
    }
}
