<?php

namespace Goldnead\Accounts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A pending change to an account.
 *
 * @property int $id
 * @property string $user_id
 * @property string $type
 * @property string|null $email
 * @property string $status
 * @property Carbon|null $due_at
 * @property Carbon|null $resolved_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AccountRequest extends Model
{
    public const TYPE_EMAIL_CHANGE = 'email_change';

    public const TYPE_DELETION = 'deletion';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * A deletion that was due but something stood in the way (a running
     * subscription, a team with other members). Still open: the purge tries
     * again every day, and it can be withdrawn.
     */
    public const STATUS_BLOCKED = 'blocked';

    protected $table = 'account_requests';

    protected $guarded = ['id'];

    protected $casts = [
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * @param  Builder<AccountRequest>  $query
     * @return Builder<AccountRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<AccountRequest>  $query
     * @return Builder<AccountRequest>
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<AccountRequest>  $query
     * @return Builder<AccountRequest>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Pending or blocked: not finished either way.
     *
     * @param  Builder<AccountRequest>  $query
     * @return Builder<AccountRequest>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_BLOCKED]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_BLOCKED], true);
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function resolve(string $status): void
    {
        $this->forceFill(['status' => $status, 'resolved_at' => now()])->save();
    }
}
