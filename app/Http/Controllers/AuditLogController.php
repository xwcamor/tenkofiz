<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use \App\Http\Controllers\Concerns\Sortable;

    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->string('module')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')));

        [$sort, $dir] = $this->applySort($logs, $request, [
            'date' => 'created_at',
            'user' => fn ($q, $d) => $q->orderBy(\App\Models\User::withTrashed()->select('name')->whereColumn('users.id', 'audit_logs.user_id'), $d),
            'action' => 'action',
            'module' => 'module',
            'ip' => 'ip',
        ], 'date', 'desc');

        $logs = $logs->paginate(50)->withQueryString();

        $modules = AuditLog::select('module')->distinct()->orderBy('module')->pluck('module');

        return view('audit.index', compact('logs', 'modules', 'sort', 'dir'));
    }
}
