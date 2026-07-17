<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->string('module')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $modules = AuditLog::select('module')->distinct()->orderBy('module')->pluck('module');

        return view('audit.index', compact('logs', 'modules'));
    }
}
