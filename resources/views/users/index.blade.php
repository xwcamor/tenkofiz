@extends('layouts.app')
@section('title', __('Users'))
@section('header-button')
    <button class="btn btn-primary" onclick="openUserModal()"><i class="fas fa-plus"></i> {{ __('New user') }}</button>
@endsection
@section('content')
<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <div class="input-group input-group-sm mr-3" style="width:280px">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Search by name or email…') }}">
                <div class="input-group-append"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
            </div>
            <select name="profile_id" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All profiles') }}</option>
                @foreach($profiles as $profile)
                    <option value="{{ $profile->id }}" @selected(request('profile_id') == $profile->id)>{{ $profile->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
            @if(request()->hasAny(['q', 'profile_id', 'status']))
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary ml-1">{{ __('Clear') }}</a>
            @endif
            <span class="ml-auto text-muted small">{{ __(':total user(s)', ['total' => $users->total()]) }}</span>
        </form>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead>
                <tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Profile') }}</th><th>{{ __('Marks attendance') }}</th><th>{{ __('Status') }}</th><th style="width:110px">{{ __('Actions') }}</th></tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>
                        @if($user->photo)
                            <img src="{{ asset($user->photo) }}" alt="" class="img-circle elevation-1 mr-1" style="width:28px;height:28px;object-fit:cover">
                        @else
                            <span class="d-inline-flex align-items-center justify-content-center img-circle mr-1"
                                  style="width:28px;height:28px;background:#e8f1fc;color:#2a78d6;font-size:.7rem;font-weight:700">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        @endif
                        {{ $user->name }} @if($user->id === auth()->id())<span class="badge badge-secondary">{{ __('you') }}</span>@endif
                    </td>
                    <td>{{ $user->email }}</td>
                    <td><span class="badge badge-primary">{{ $user->profile?->name ?? '—' }}</span></td>
                    <td>
                        @if($user->employee)
                            <span class="badge badge-success" title="{{ $user->employee->document_type }} {{ $user->employee->document_number }}"><i class="fas fa-id-badge"></i> {{ $user->employee->full_name }}</span>
                        @else
                            <span class="text-muted small">{{ __('Does not mark (admin account)') }}</span>
                        @endif
                    </td>
                    <td><span class="badge badge-{{ $user->is_active ? 'success' : 'secondary' }}">{{ $user->is_active ? __('Active') : __('Inactive') }}</span></td>
                    <td>
                        @php
                            $payload = json_encode([
                                'action' => route('users.update', $user),
                                'name' => $user->name,
                                'email' => $user->email,
                                'profile_id' => $user->profile_id,
                                'is_active' => $user->is_active,
                            ]);
                        @endphp
                        <button class="btn btn-sm btn-info" title="{{ __('Edit') }}" data-payload="{{ $payload }}" onclick="openUserModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                        @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                            </form>
                        @else
                            <button class="btn btn-sm btn-danger" disabled title="{{ __('You cannot delete your own account.') }}"><i class="fas fa-trash"></i></button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No users match the current filters.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="text-muted small">
                @if($users->total())
                    {{ __('Showing :from–:to of :total', ['from' => $users->firstItem(), 'to' => $users->lastItem(), 'total' => $users->total()]) }}
                @endif
            </span>
            {{ $users->links() }}
        </div>
    </div>
</div>

{{-- Create / edit modal --}}
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ old('_form_action', route('users.store')) }}" enctype="multipart/form-data" class="modal-content" id="userForm">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}" id="userMethod">
            <input type="hidden" name="_form_action" value="{{ old('_form_action', route('users.store')) }}" id="userFormAction">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-cog"></i> {{ __('User') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Full name') }}</label>
                    <input name="name" id="userName" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Email address') }}</label>
                    <input type="email" name="email" id="userEmail" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                    @error('email')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Password') }} <small class="text-muted" id="userPasswordHint">({{ __('leave empty to keep the current one') }})</small></label>
                    <input type="password" name="password" id="userPassword" class="form-control @error('password') is-invalid @enderror">
                    @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Profile') }}</label>
                    <select name="profile_id" id="userProfile" class="form-control @error('profile_id') is-invalid @enderror" required>
                        <option value="">— {{ __('Select') }} —</option>
                        @foreach($profiles as $profile)
                            <option value="{{ $profile->id }}" @selected(old('profile_id') == $profile->id)>{{ $profile->name }}</option>
                        @endforeach
                    </select>
                    @error('profile_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    <small class="text-muted">{{ __('The profile defines which modules the user can see (configure it in Profiles).') }}</small>
                </div>
                <div class="form-group">
                    <label>{{ __('Photo') }} <small class="text-muted">({{ __('optional; PNG/JPG, max. 2MB') }})</small></label>
                    <input type="file" name="photo" class="form-control-file @error('photo') is-invalid @enderror" accept="image/png,image/jpeg,image/webp">
                    @error('photo')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    <small class="text-muted" id="userPhotoHint" style="display:none">{{ __('Uploading a new photo replaces the current one.') }}</small>
                </div>
                <div class="custom-control custom-switch">
                    <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="userActive" @checked(old('is_active', true))>
                    <label class="custom-control-label" for="userActive">{{ __('Active') }}</label>
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
const USER_STORE_URL = @json(route('users.store'));

function openUserModal(data = null) {
    const form = document.getElementById('userForm');
    form.action = data ? data.action : USER_STORE_URL;
    document.getElementById('userFormAction').value = form.action;
    document.getElementById('userMethod').value = data ? 'PUT' : 'POST';
    document.getElementById('userName').value = data ? data.name : '';
    document.getElementById('userEmail').value = data ? data.email : '';
    document.getElementById('userProfile').value = data ? (data.profile_id || '') : '';
    document.getElementById('userActive').checked = data ? !!data.is_active : true;
    const password = document.getElementById('userPassword');
    password.value = '';
    password.required = !data;
    document.getElementById('userPasswordHint').style.display = data ? '' : 'none';
    document.getElementById('userPhotoHint').style.display = data ? '' : 'none';
    $('#userModal').modal('show');
}

@if($errors->any())
    $('#userModal').modal('show');
@endif
</script>
@endpush
