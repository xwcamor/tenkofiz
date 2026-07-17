@extends('layouts.app')
@section('title', __('System audit log'))
@section('content')
<div class="alert alert-info"><i class="fas fa-shield-alt"></i> {{ __('Log of sensitive actions: deletions, manual attendance edits and user creation. Times are shown in your timezone (:tz).', ['tz' => user_timezone()]) }}</div>
<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <select name="module" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All modules') }}</option>
                @foreach($modules as $module)
                    <option value="{{ $module }}" @selected(request('module') == $module)>{{ __($module) }}</option>
                @endforeach
            </select>
            <select name="action" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All actions') }}</option>
                @foreach(['CREATE', 'UPDATE', 'DELETE'] as $action)
                    <option value="{{ $action }}" @selected(request('action') == $action)>{{ __($action) }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
        </form>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead><tr><th>{{ __('Date and time') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Module') }}</th><th>{{ __('Description') }}</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ to_user_tz($log->created_at)->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? __('System') }}</td>
                    <td><span class="badge badge-{{ $log->action === 'DELETE' ? 'danger' : ($log->action === 'UPDATE' ? 'warning' : 'success') }}">{{ __($log->action) }}</span></td>
                    <td>{{ __($log->module) }}</td>
                    <td>{{ $log->description }}</td>
                    <td class="text-muted">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No audit records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="d-flex justify-content-center">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
