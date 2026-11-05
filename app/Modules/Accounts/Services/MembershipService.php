<?php

namespace App\Modules\Accounts\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Core\Permissions\Role;
use App\Modules\Accounts\Models\Invitation;
use App\Modules\Accounts\Models\StoreMember;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Notifications\StoreInvitationNotification;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invite staff by email with a role, accept invitations, change roles, revoke access. FR-ACC-04
 */
class MembershipService
{
    public const INVITE_DAYS = 7;

    public function __construct(private readonly ActivityLogger $activity) {}

    public function invite(Store $store, User $by, string $email, Role $role): Invitation
    {
        $email = strtolower(trim($email));

        if ($role === Role::Owner) {
            throw ValidationException::withMessages(['role' => __('dashboard.members.cannot_invite_owner')]);
        }

        $alreadyMember = $store->members()->whereHas('user', fn ($q) => $q->where('email', $email))->exists();
        if ($alreadyMember) {
            throw ValidationException::withMessages(['email' => __('dashboard.members.already_member')]);
        }

        $token = Str::random(48);

        $invitation = DB::transaction(function () use ($store, $by, $email, $role, $token) {
            // A new invite replaces any pending one for the same email.
            Invitation::withoutStoreScope()
                ->where('store_id', $store->getKey())
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return Invitation::query()->create([
                'store_id' => $store->getKey(),
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $by->getKey(),
                'expires_at' => now()->addDays(self::INVITE_DAYS),
            ]);
        });

        $this->activity->log('member.invited', $invitation, ['email' => $email, 'role' => $role->value], storeId: $store->getKey());

        Notification::route('mail', $email)
            ->notify((new StoreInvitationNotification($invitation, $store, $token))->locale($store->default_locale));

        return $invitation;
    }

    public function findPendingByToken(string $token): ?Invitation
    {
        $invitation = Invitation::withoutStoreScope()->where('token_hash', hash('sha256', $token))->first();

        return $invitation?->isPending() ? $invitation : null;
    }

    /**
     * Accept an invitation as a signed-in user, or create the account for a new person.
     */
    public function accept(Invitation $invitation, User $user): StoreMember
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages(['invitation' => __('dashboard.members.invitation_invalid')]);
        }

        if (strtolower($user->email) !== $invitation->email) {
            throw ValidationException::withMessages(['invitation' => __('dashboard.members.invitation_wrong_email')]);
        }

        return DB::transaction(function () use ($invitation, $user) {
            $invitation->accepted_at = now();
            $invitation->save();

            // Opening the link from the email proves the address.
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $member = StoreMember::withoutStoreScope()->firstOrCreate(
                ['store_id' => $invitation->store_id, 'user_id' => $user->getKey()],
                ['role' => $invitation->role],
            );

            $this->activity->log('member.joined', $member, ['role' => $invitation->role->value], storeId: $invitation->store_id);

            return $member;
        });
    }

    public function changeRole(StoreMember $member, Role $role): StoreMember
    {
        $this->guardLastOwner($member, $role);

        $from = $member->role;
        $member->role = $role;
        $member->save();

        $this->activity->log('member.role_changed', $member, ['from' => $from->value, 'to' => $role->value], storeId: $member->store_id);

        return $member;
    }

    public function revoke(StoreMember $member): void
    {
        $this->guardLastOwner($member, null);

        $this->activity->log('member.revoked', $member, ['user_id' => $member->user_id, 'role' => $member->role->value], storeId: $member->store_id);

        $member->delete();
    }

    public function revokeInvitation(Invitation $invitation): void
    {
        $invitation->revoked_at = now();
        $invitation->save();

        $this->activity->log('member.invitation_revoked', $invitation, ['email' => $invitation->email], storeId: $invitation->store_id);
    }

    /** A store always keeps at least one owner. */
    private function guardLastOwner(StoreMember $member, ?Role $newRole): void
    {
        if ($member->role !== Role::Owner || $newRole === Role::Owner) {
            return;
        }

        $owners = StoreMember::withoutStoreScope()
            ->where('store_id', $member->store_id)
            ->where('role', Role::Owner->value)
            ->count();

        if ($owners <= 1) {
            throw ValidationException::withMessages(['member' => __('dashboard.members.last_owner')]);
        }
    }
}
