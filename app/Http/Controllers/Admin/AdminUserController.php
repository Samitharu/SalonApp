<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SystemLog;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Admin users and what each may do. The rules that keep this safe:
 *  - nobody changes their own access (no self-promotion, no accidental lock-out);
 *  - only owners can touch owners, or make someone an owner;
 *  - nobody can grant a permission they don't hold themselves, or take over
 *    the account of someone who holds one;
 *  - there is always at least one active owner.
 */
class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.users.index', [
            'users' => Admin::orderByDesc('is_active')->orderByDesc('is_owner')->orderBy('name')->get(),
            'actor' => $request->user('admin'),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.users.form', ['user' => new Admin(), 'actor' => $request->user('admin')]);
    }

    public function store(Request $request)
    {
        $actor = $request->user('admin');
        $data = $this->validated($request);
        $isOwner = $actor->is_owner && $request->boolean('is_owner');

        $user = Admin::create([
            'name'        => $data['name'],
            'email'       => strtolower(trim($data['email'])),
            'password'    => Hash::make($data['password']),
            'is_owner'    => $isOwner,
            'is_active'   => true,
            'permissions' => $isOwner ? [] : $this->grantable($actor, new Admin(), $data['permissions'] ?? []),
        ]);

        SystemLog::record('user.created', "Admin user {$user->name} ({$user->email}) added as " . $user->roleLabel(), $user,
            ['email' => $user->email, 'owner' => $isOwner, 'permissions' => $this->labels($user->permissions ?? [])]);

        return redirect()->route('admin.users.index')->with('success', "{$user->name} can now sign in at /admin/login with the password you set.");
    }

    public function edit(Request $request, Admin $user)
    {
        $actor = $request->user('admin');

        if ($actor->is($user)) {
            return redirect()->route('admin.account.edit')->with('success', 'This is your own account. Your access can only be changed by an owner.');
        }
        $this->ensureCanManage($actor, $user);

        return view('admin.users.form', compact('user', 'actor'));
    }

    public function update(Request $request, Admin $user)
    {
        $actor = $request->user('admin');
        abort_if($actor->is($user), 403, 'You can’t change your own access. Ask another owner.');
        $this->ensureCanManage($actor, $user);

        $data = $this->validated($request, $user);

        // Setting the password of someone with more access would let the actor sign in as them.
        if (!empty($data['password']) && !$actor->is_owner && array_diff($user->effectivePermissions(), $actor->effectivePermissions())) {
            throw ValidationException::withMessages(['password' => 'Only an owner can reset the password of someone with access you don’t have.']);
        }

        $isOwner = $actor->is_owner ? $request->boolean('is_owner') : false;
        $isActive = $request->boolean('is_active');
        $before = $user->permissions ?? [];
        $wasOwner = $user->is_owner;

        DB::transaction(function () use ($user, $data, $isOwner, $isActive, $actor) {
            if ($user->is_owner && (!$isOwner || !$isActive)) {
                $this->ensureAnotherOwner($user);
            }

            $user->fill([
                'name'        => $data['name'],
                'email'       => strtolower(trim($data['email'])),
                'is_owner'    => $isOwner,
                'is_active'   => $isActive,
                'permissions' => $isOwner ? [] : $this->grantable($actor, $user, $data['permissions'] ?? []),
            ]);
            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            // A new password or deactivation also ends "keep me signed in" on their devices.
            if (!empty($data['password']) || !$isActive) {
                $user->setRememberToken(Str::random(60));
            }
            $user->save();
        });

        $this->logChanges($user, $before, $wasOwner, !empty($data['password']));

        return redirect()->route('admin.users.index')->with('success', "{$user->name} updated. Changes apply straight away.");
    }

    public function destroy(Request $request, Admin $user)
    {
        $actor = $request->user('admin');

        if ($actor->is($user)) {
            return back()->with('error', 'You can’t delete your own account.');
        }
        $this->ensureCanManage($actor, $user);

        DB::transaction(function () use ($user) {
            if ($user->is_owner) {
                $this->ensureAnotherOwner($user);
            }
            SystemLog::record('user.deleted', "Admin user {$user->name} ({$user->email}) deleted", $user, ['email' => $user->email], 'warning');
            $user->delete();
        });

        return back()->with('success', "{$user->name} was deleted.");
    }

    private function ensureCanManage(Admin $actor, Admin $user): void
    {
        abort_if($user->is_owner && !$actor->is_owner, 403, 'Only an owner can change an owner’s account.');
    }

    /** The safety net: the system can never be left without an active owner. */
    private function ensureAnotherOwner(Admin $user): void
    {
        $others = Admin::where('is_owner', true)->where('is_active', true)->whereKeyNot($user->id)->lockForUpdate()->count();

        if ($others === 0) {
            throw ValidationException::withMessages(['is_owner' => 'This is the only active owner. Make someone else an owner first.']);
        }
    }

    /**
     * What gets saved: the ticked boxes the actor is allowed to hand out, plus
     * anything the user already holds that the actor has no say over.
     */
    private function grantable(Admin $actor, Admin $target, array $submitted): array
    {
        $submitted = Permissions::normalise($submitted);

        if ($actor->is_owner) {
            return $submitted;
        }

        $mine = $actor->effectivePermissions();

        return Permissions::normalise(array_merge(
            array_intersect($submitted, $mine),
            array_diff($target->permissions ?? [], $mine),
        ));
    }

    private function validated(Request $request, ?Admin $user = null): array
    {
        return $request->validate([
            'name'          => 'required|string|max:120',
            'email'         => ['required', 'email:rfc', 'max:120', Rule::unique('admins', 'email')->ignore($user?->id)],
            'password'      => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'permissions'   => 'nullable|array',
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ], [
            'email.unique' => 'Another user already has this email.',
        ]);
    }

    private function logChanges(Admin $user, array $before, bool $wasOwner, bool $passwordChanged): void
    {
        $changed = SystemLog::changes($user, ['permissions', 'remember_token']);
        $granted = array_diff($user->permissions ?? [], $before);
        $revoked = array_diff($before, $user->permissions ?? []);

        if ($granted || $revoked) {
            $changed['permissions'] = ['granted' => $this->labels($granted), 'revoked' => $this->labels($revoked)];
        }
        if ($passwordChanged) {
            SystemLog::record('user.password_reset', "Password reset for admin user {$user->name}", $user, level: 'warning');
        }
        if ($changed) {
            // Less access or owner changes matter most when reviewing the log.
            $serious = $revoked || $wasOwner !== $user->is_owner || isset($changed['is_active']);
            SystemLog::record('user.updated', "Admin user {$user->name} updated: " . implode(', ', array_map(fn ($k) => str_replace(['is_', '_'], ['', ' '], $k), array_keys($changed))), $user, $changed, $serious ? 'warning' : 'info');
        }
    }

    private function labels(array $permissions): array
    {
        return array_values(array_map([Permissions::class, 'label'], $permissions));
    }
}
