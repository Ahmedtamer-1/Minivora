<?php

// Dashboard text used from PHP (flash messages, errors). React strings live in lang/en.json.

return [
    'saved' => 'Saved.',
    'roles' => [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'accountant' => 'Accountant',
        'staff' => 'Staff',
    ],
    'stores' => [
        'created' => 'Your new store is ready.',
    ],
    'onboarding' => [
        'saved' => 'Thanks! Your store draft will be based on these answers.',
    ],
    'publish' => [
        'done' => 'Your store is live.',
        'undone' => 'Your store is back to “opening soon”.',
        'verify_email_first' => 'Verify your email before publishing.',
        'suspended' => 'This store is suspended and cannot be published.',
    ],
    'verify' => [
        'sent' => 'A new verification link is on its way.',
        'done' => 'Email verified.',
    ],
    'members' => [
        'invited' => 'Invitation sent to :email.',
        'revoked' => 'Access removed.',
        'invitation_revoked' => 'Invitation cancelled.',
        'already_member' => 'This person is already a member of the store.',
        'cannot_invite_owner' => 'Owners cannot be invited.',
        'last_owner' => 'A store must keep at least one owner.',
        'invitation_invalid' => 'This invitation is no longer valid.',
        'invitation_wrong_email' => 'This invitation was sent to a different email address.',
    ],
    'admin' => [
        'suspended' => 'Store suspended.',
        'restored' => 'Store restored.',
    ],
];
