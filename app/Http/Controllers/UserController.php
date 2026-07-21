<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use \App\Http\Controllers\Concerns\Sortable;

    public function index(Request $request)
    {
        // Server-side pagination + search so the list stays fast with thousands of accounts.
        // 'employee' tells apart people who mark attendance from admin-only accounts.
        $search = trim((string) $request->input('q'));

        // Deleted-records view: restricted to administrators (settings module)
        $showDeleted = $request->boolean('deleted') && $request->user()->hasModule('settings');

        $users = User::with(['profile', 'employee', 'site'])
            ->inCompany()
            ->when($showDeleted, fn ($q) => $q->onlyTrashed())
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($request->filled('profile_id'), fn ($q) => $q->where('profile_id', $request->integer('profile_id')))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false));

        [$sort, $dir] = $this->applySort($users, $request, [
            'name' => 'name',
            'email' => 'email',
            'profile' => fn ($q, $d) => $q->orderBy(Profile::select('name')->whereColumn('profiles.id', 'users.profile_id'), $d),
            'site' => fn ($q, $d) => $q->orderBy(Site::select('name')->whereColumn('sites.id', 'users.site_id'), $d),
            'status' => 'is_active',
        ], 'name');

        $users = $users->paginate(25)->withQueryString();

        $profiles = Profile::where('is_active', true)->orderBy('name')->get();
        $sites = Site::where('is_active', true)->orderBy('name')->get();
        return view('users.index', compact('users', 'profiles', 'sites', 'showDeleted', 'sort', 'dir'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->withoutTrashed()],
            'password' => ['required', 'string', 'min:6'],
            'profile_id' => ['required', 'exists:profiles,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = true; // new users are always active (toggle only on edit)
        $data['site_id'] = $this->resolveSiteId($request, $data['site_id'] ?? null);
        $data['photo'] = $this->storePhoto($request);
        $user = User::create($data);

        // Welcome email with the sign-in link and credentials
        safe_mail(
            $user->email,
            __('Your access credentials for the Attendance System'),
            __("Hello :name,\n\nYour access account was created:\nEmail: :email\nInitial password: :password\n\nSign in here: :url\n\nRegards.", [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $request->input('password'),
                'url' => route('login'),
            ])
        );

        return redirect()->route('users.index')->with('ok', __('User created successfully. The credentials were emailed with the sign-in link.'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)->withoutTrashed()],
            'password' => ['nullable', 'string', 'min:6'],
            'profile_id' => ['required', 'exists:profiles,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['is_active'] = $request->boolean('is_active');
        $data['site_id'] = $this->resolveSiteId($request, $data['site_id'] ?? null);

        if ($photo = $this->storePhoto($request, $user)) {
            $data['photo'] = $photo;
        } else {
            unset($data['photo']);
        }

        // A user cannot deactivate their own account
        if ($user->id === auth()->id() && !$data['is_active']) {
            return back()->with('error', __('You cannot deactivate your own account.'))->withInput();
        }

        $user->update($data);

        return redirect()->route('users.index')->with('ok', __('User updated.'));
    }

    public function destroy(Request $request, User $user)
    {
        // The logged-in user can never delete their own account
        if ($user->id === auth()->id()) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        $data = $request->validate(['delete_reason' => ['required', 'string', 'max:300']],
            ['delete_reason.required' => __('The deletion reason is required.')]);

        // Soft delete: the account can no longer sign in, but stays recoverable
        $user->update(['delete_reason' => $data['delete_reason']]);
        $user->delete();

        AuditLog::record('DELETE', 'Users',
            __('User :name (:email) was deleted. Reason: :reason', [
                'name' => $user->name,
                'email' => $user->email,
                'reason' => $data['delete_reason'],
            ]),
            $user->toArray());

        return back()->with('ok', __('User deleted. An administrator can restore it from "View deleted".'));
    }

    /** Brings a soft-deleted user back (administrators only) */
    public function restore(Request $request, User $user)
    {
        abort_unless($request->user()->hasModule('settings'), 403);

        $user->restore();
        $user->update(['delete_reason' => null]);

        AuditLog::record('UPDATE', 'Users',
            __('User :name (:email) was restored', ['name' => $user->name, 'email' => $user->email]));

        return back()->with('ok', __('User restored.'));
    }

    /**
     * A company/system admin (no site) freely assigns any site or leaves it blank
     * (company-wide). A site-bound admin can only ever create users inside their
     * own site — the selector is ignored and forced to their site.
     */
    private function resolveSiteId(Request $request, ?int $chosen): ?int
    {
        $current = $request->user();

        return $current->isSiteBound() ? $current->site_id : $chosen;
    }

    /** Saves the avatar center-cropped to a 256px square JPEG and removes the previous one */
    private function storePhoto(Request $request, ?User $user = null): ?string
    {
        if (!$request->hasFile('photo')) {
            return null;
        }

        $dir = public_path('uploads/avatars');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = uniqid('avatar_').'.jpg';
        $source = imagecreatefromstring(file_get_contents($request->file('photo')->getRealPath()));

        // Center-crop to a square, then scale down to 256x256
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $avatar = imagecreatetruecolor(256, 256);
        imagecopyresampled(
            $avatar, $source,
            0, 0,
            (int) (($width - $side) / 2), (int) (($height - $side) / 2),
            256, 256, $side, $side
        );
        imagejpeg($avatar, $dir.'/'.$name, 88);
        imagedestroy($source);
        imagedestroy($avatar);

        if ($user?->photo && is_file(public_path($user->photo))) {
            @unlink(public_path($user->photo));
        }

        return 'uploads/avatars/'.$name;
    }
}
