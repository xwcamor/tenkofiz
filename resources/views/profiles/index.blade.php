@extends('layouts.app')
@section('title', __('Profiles'))
@section('header-button')
    <button class="btn btn-primary" onclick="openProfileModal()"><i class="fas fa-plus"></i> {{ __('New profile') }}</button>
@endsection
@section('content')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table" data-server-sort>
            <thead><tr>
                @include('partials.th-sort', ['key' => 'name', 'label' => __('Name')])
                <th>{{ __('Description') }}</th>
                <th>{{ __('Permissions') }}</th>
                @include('partials.th-sort', ['key' => 'users', 'label' => __('Users')])
                @include('partials.th-sort', ['key' => 'status', 'label' => __('Status')])
                <th style="width:110px">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse($profiles as $profile)
                <tr>
                    <td>
                        <strong>{{ $profile->name }}</strong>
                        @if($profile->is_system)
                            <span class="badge badge-dark ml-1" title="{{ __('Base role: cannot be deleted or renamed') }}"><i class="fas fa-lock"></i> {{ __('System') }}</span>
                        @endif
                    </td>
                    <td>{{ $profile->description }}</td>
                    <td>
                        @forelse($profile->permissions ?? [] as $permission)
                            <span class="badge badge-secondary">{{ __(\App\Models\Profile::MODULES[$permission] ?? $permission) }}</span>
                        @empty
                            <span class="text-muted">{{ __('Self-service only') }}</span>
                        @endforelse
                    </td>
                    <td><span class="badge badge-info">{{ $profile->users_count }}</span></td>
                    <td><span class="badge badge-{{ $profile->is_active ? 'success' : 'secondary' }}">{{ $profile->is_active ? __('Active') : __('Inactive') }}</span></td>
                    <td>
                        @php
                            $payload = json_encode([
                                'action' => route('profiles.update', $profile),
                                'name' => $profile->name,
                                'description' => $profile->description,
                                'permissions' => $profile->permissions ?? [],
                                'is_active' => $profile->is_active,
                                'is_system' => (bool) $profile->is_system,
                                'is_admin_role' => $profile->isAdministratorRole(),
                            ]);
                        @endphp
                        <button class="btn btn-sm btn-info" data-payload="{{ $payload }}" onclick="openProfileModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                        @if($profile->is_system)
                            <button class="btn btn-sm btn-danger" disabled title="{{ __('Base role: cannot be deleted') }}"><i class="fas fa-lock"></i></button>
                        @else
                            <form method="POST" action="{{ route('profiles.destroy', $profile) }}" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No profiles yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Create / edit modal --}}
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ old('_form_action', route('profiles.store')) }}" class="modal-content" id="profileForm">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}" id="profileMethod">
            <input type="hidden" name="_form_action" value="{{ old('_form_action', route('profiles.store')) }}" id="profileFormAction">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-shield-alt"></i> {{ __('Profile') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Profile name') }}</label>
                    <input name="name" id="profileName" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Description') }}</label>
                    <textarea name="description" id="profileDescription" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="form-group">
                    <label>{{ __('Modules this profile can see') }}</label>
                    <div class="border rounded p-2">
                        @foreach(\App\Models\Profile::MODULES as $key => $label)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="permissions[]" value="{{ $key }}" class="custom-control-input profile-permission" id="perm_{{ $key }}"
                                       @checked(in_array($key, old('permissions', []), true))>
                                <label class="custom-control-label" for="perm_{{ $key }}">{{ __($label) }}</label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted">{{ __('Without checked modules, the user only sees their own information (self-service).') }}</small>
                    @error('permissions.*')<span class="text-danger d-block">{{ $message }}</span>@enderror
                </div>
                <div class="custom-control custom-switch" id="profileActiveRow">
                    <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="profileActive" @checked(old('is_active', true))>
                    <label class="custom-control-label" for="profileActive">{{ __('Active') }}</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const PROFILE_STORE_URL = @json(route('profiles.store'));

function openProfileModal(data = null) {
    const form = document.getElementById('profileForm');
    form.action = data ? data.action : PROFILE_STORE_URL;
    document.getElementById('profileFormAction').value = form.action;
    document.getElementById('profileMethod').value = data ? 'PUT' : 'POST';
    document.getElementById('profileName').value = data ? data.name : '';
    document.getElementById('profileDescription').value = data ? (data.description || '') : '';
    document.getElementById('profileActive').checked = data ? !!data.is_active : true;
    document.getElementById('profileActiveRow').style.display = data ? '' : 'none';
    document.querySelectorAll('.profile-permission').forEach(box => {
        box.checked = data ? data.permissions.includes(box.value) : false;
    });
    // Base system roles: lock the name, keep them active, and (Administrator) lock
    // every permission on. The server enforces this too; this just mirrors it.
    const isSystem = !!(data && data.is_system);
    const isAdminRole = !!(data && data.is_admin_role);
    document.getElementById('profileName').readOnly = isSystem;
    document.getElementById('profileActive').disabled = isSystem;
    if (isSystem) document.getElementById('profileActiveRow').style.display = 'none';
    document.querySelectorAll('.profile-permission').forEach(box => {
        if (isAdminRole) box.checked = true;
        box.disabled = isAdminRole;
    });
    $('#profileModal').modal('show');
}

@if($errors->any())
    $('#profileModal').modal('show');
@endif
</script>
@endpush
