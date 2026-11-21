<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A key for Power BI, Power Automate or an agent to read the portal's data through the API. Only a hash is stored. */
class ApiKey extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'name', 'key_hash', 'prefix', 'created_by'];

    protected $casts = ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];

    /** @return array{0: ApiKey, 1: string} the key and its secret value (shown once) */
    public static function issue(int $accountId, string $name, ?int $userId): array
    {
        $secret = 'cmp_'.Str::random(40);
        $key = self::create(['account_id' => $accountId, 'name' => $name, 'key_hash' => hash('sha256', $secret),
            'prefix' => substr($secret, 0, 10), 'created_by' => $userId]);

        return [$key, $secret];
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function findActive(?string $secret): ?self
    {
        if (! $secret || ! str_starts_with($secret, 'cmp_')) {
            return null;
        }

        return self::withoutGlobalScopes()->where('key_hash', hash('sha256', $secret))->whereNull('revoked_at')->first();
    }
}
