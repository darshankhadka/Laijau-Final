<?php

declare(strict_types=1);

namespace App\Policies\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\User;
use App\Policies\BasePolicy;

class JournalEntryPolicy extends BasePolicy
{
    protected string $modelName = 'JournalEntry';

    protected array $allowedRoles = [
        'Accountant',
    ];

    /**
     * Determine whether the user can post a journal entry.
     */
    public function post(User $user, ?JournalEntry $entry = null): bool
    {
        return $this->authorize($user, 'Post', $entry);
    }

    /**
     * Determine whether the user can reverse a journal entry.
     */
    public function reverse(User $user, ?JournalEntry $entry = null): bool
    {
        return $this->authorize($user, 'Reverse', $entry);
    }
}
