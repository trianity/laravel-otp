<?php

declare(strict_types=1);

namespace Trianity\Otp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $identifier
 * @property string $token Hashed OTP token.
 * @property int $validity
 * @property bool $expired
 * @property int $no_times_generated
 * @property int $no_times_attempted
 * @property Carbon $generated_at
 */
class Otp extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'identifier', 'token', 'validity', 'expired', 'no_times_generated', 'generated_at',
    ];

    protected $casts = [
        'expired' => 'boolean',
        'generated_at' => 'datetime',
        'no_times_attempted' => 'integer',
        'no_times_generated' => 'integer',
        'validity' => 'integer',
    ];

    public function isExpired(): bool
    {
        if ($this->expired) {
            return true;
        }

        $generatedTime = $this->generated_at->addMinutes($this->validity);

        if (strtotime(strval($generatedTime)) >= strtotime(Carbon::now()->toDateTimeString())) {
            return false;
        }

        $this->markExpired();

        return true;
    }

    public function expiredAt(): object
    {
        return $this->generated_at->addMinutes($this->validity);
    }

    public function incrementAttempts(): void
    {
        $this->increment('no_times_attempted', 1, [
            'generated_at' => $this->generated_at,
        ]);
    }

    public function markExpired(): void
    {
        $this->expired = true;
        $this->original['generated_at'] = null;
        $this->save();
    }
}
