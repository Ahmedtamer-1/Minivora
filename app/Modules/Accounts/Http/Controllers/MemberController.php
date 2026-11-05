<?php

namespace App\Modules\Accounts\Http\Controllers;

use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\Invitation;
use App\Modules\Accounts\Models\StoreMember;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff and roles: invite, change role, revoke. Owner only (SRS §7.2). FR-ACC-04
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly MembershipService $memberships,
    ) {}

    public function index(Request $request): Response
    {
        $members = StoreMember::query()->with('user:id,name,email')->orderBy('created_at')->get()
            ->map(fn (StoreMember $m) => [
                'id' => $m->id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'role' => $m->role->value,
                'is_you' => $m->user_id === $request->user()?->getAuthIdentifier(),
            ]);

        $invitations = Invitation::query()->whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())->latest()->get()
            ->map(fn (Invitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role->value,
                'expires_at' => $i->expires_at->toIso8601String(),
            ]);

        return Inertia::render('settings/members', [
            'members' => $members,
            'invitations' => $invitations,
            'roles' => array_map(fn (Role $r) => $r->value, Role::cases()),
            'invitableRoles' => array_map(fn (Role $r) => $r->value, Role::invitable()),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_map(fn (Role $r) => $r->value, Role::invitable()))],
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->memberships->invite($this->current->getOrFail(), $user, $data['email'], Role::from($data['role']));

        return back()->with('success', __('dashboard.members.invited', ['email' => $data['email']]));
    }

    public function update(Request $request, int $member): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        $this->memberships->changeRole(StoreMember::query()->findOrFail($member), Role::from($data['role']));

        return back()->with('success', __('dashboard.saved'));
    }

    public function destroy(int $member): RedirectResponse
    {
        $this->memberships->revoke(StoreMember::query()->findOrFail($member));

        return back()->with('success', __('dashboard.members.revoked'));
    }

    public function revokeInvitation(int $invitation): RedirectResponse
    {
        $this->memberships->revokeInvitation(Invitation::query()->findOrFail($invitation));

        return back()->with('success', __('dashboard.members.invitation_revoked'));
    }
}
