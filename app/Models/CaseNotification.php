<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseNotification extends Model
{
    use UsesUuid;

    protected $fillable = [
        'case_id',
        'client_email',
        'type',
        'title',
        'message',
        'data',
        'related_url',
        'read_at',
        'event_key',
        'delivery_status',
        'delivery_attempted_at',
        'claim_token',
        'claim_generation',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'delivery_attempted_at' => 'datetime',
        'claim_generation' => 'integer',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    /**
     * Atomically reserve a notification delivery for an event.
     *
     * A unique event key is the durable idempotency boundary. Failed claims
     * may be reclaimed, while an active claim is left to its original worker.
     */
    public static function claimDelivery(array $attributes): ?self
    {
        $now = now();
        $claimToken = (string) Str::uuid();
        if (array_key_exists('data', $attributes) && is_array($attributes['data'])) {
            $attributes['data'] = json_encode($attributes['data'], JSON_THROW_ON_ERROR);
        }

        $inserted = static::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            ...$attributes,
            'delivery_status' => 'processing',
            'delivery_attempted_at' => $now,
            'claim_token' => $claimToken,
            'claim_generation' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 1) {
            return static::query()->where('event_key', $attributes['event_key'])->first();
        }

        $reclaimed = static::query()
            ->where('event_key', $attributes['event_key'])
            ->where(function ($query) use ($now): void {
                $query->where('delivery_status', 'failed')
                    ->orWhere(function ($query) use ($now): void {
                        $query->where('delivery_status', 'processing')
                            ->where('delivery_attempted_at', '<', $now->copy()->subMinutes(30));
                    });
            })
            ->update([
                'delivery_status' => 'processing',
                'delivery_attempted_at' => $now,
                'claim_token' => $claimToken,
                'claim_generation' => DB::raw('claim_generation + 1'),
                'updated_at' => $now,
            ]);

        if ($reclaimed !== 1) {
            return null;
        }

        return static::query()
            ->where('event_key', $attributes['event_key'])
            ->where('claim_token', $claimToken)
            ->first();
    }

    public function markDeliveryQueued(): void
    {
        static::query()
            ->whereKey($this->getKey())
            ->where('claim_token', $this->claim_token)
            ->where('claim_generation', $this->claim_generation)
            ->where('delivery_status', 'processing')
            ->update(['delivery_status' => 'queued', 'updated_at' => now()]);
    }

    public function markDeliveryFailed(): void
    {
        static::query()
            ->whereKey($this->getKey())
            ->where('claim_token', $this->claim_token)
            ->where('claim_generation', $this->claim_generation)
            ->whereIn('delivery_status', ['processing', 'queued'])
            ->update([
                'delivery_status' => 'failed',
                'delivery_attempted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function markDeliverySent(): void
    {
        static::query()
            ->whereKey($this->getKey())
            ->where('claim_token', $this->claim_token)
            ->where('claim_generation', $this->claim_generation)
            ->where('delivery_status', 'queued')
            ->update(['delivery_status' => 'sent', 'updated_at' => now()]);
    }
}
