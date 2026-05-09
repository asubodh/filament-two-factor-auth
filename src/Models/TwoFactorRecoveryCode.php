<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a single hashed recovery code for a user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property \Carbon\Carbon|null $used_at
 * @property \Carbon\Carbon|null $created_at
 */
class TwoFactorRecoveryCode extends Model
{
    /**
     * Indicates if the model should be timestamped.
     * We manage created_at manually; there is no updated_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'code_hash',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('two-factor-auth.recovery_codes.table', 'two_factor_recovery_codes');
    }

    /**
     * Get the user that owns this recovery code.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    /**
     * Determine if this recovery code has been used.
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Mark this recovery code as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
