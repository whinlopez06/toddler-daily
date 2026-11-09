<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DemoAccount extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime', 'last_login_at' => 'immutable_datetime',
            'expiry_recorded_at' => 'immutable_datetime', 'records_created' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function active(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function recordEvent(string $event): void
    {
        DB::transaction(function () use ($event) {
            self::whereKey($this->user_id)->lockForUpdate()->firstOrFail();
            if (DB::table('demo_access_events')->where('user_id', $this->user_id)->count() < config('demo.events_per_account')) {
                DB::table('demo_access_events')->insert(['user_id' => $this->user_id, 'event' => $event, 'occurred_at' => now()]);
            }
        });
    }

    public function recordExpiry(): void
    {
        DB::transaction(function () {
            $changed = self::whereKey($this->user_id)->whereNull('expiry_recorded_at')->update(['expiry_recorded_at' => now()]);
            if ($changed) {
                $this->recordEvent('expired');
            }
        });
    }
}
