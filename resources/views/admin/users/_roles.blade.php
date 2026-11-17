{{-- Role switches of the user forms: the role's Arabic name and what it opens (built-in roles), its system name in small type. Needs $roles, $checkedRoles. --}}
<div class="row g-2">
    @foreach($roles as $role)
        @php($roleDescription = \App\Support\AdminLabels::roleDescription($role->name))
        <div class="col-md-6 col-xxl-4">
            <label class="toggle-row" for="role_{{ $role->id }}">
                <span class="tr-text">
                    <span class="tr-label d-block">
                        {{ \App\Support\AdminLabels::role($role->name) }}
                        @if($roleDescription !== null)<span class="ltr text-muted small fw-normal text-nowrap ms-1">{{ $role->name }}</span>@endif
                    </span>
                    <span class="tr-desc d-block">{{ $roleDescription ?? __('الصلاحيات: :count', ['count' => $role->permissions->count()]) }}</span>
                </span>
                <span class="switch">
                    <input type="checkbox" role="switch" name="roles[]" value="{{ $role->name }}" id="role_{{ $role->id }}"
                           @checked(in_array($role->name, $checkedRoles))>
                    <span class="track"></span>
                </span>
            </label>
        </div>
    @endforeach
</div>
@error('roles')
    <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
@enderror
