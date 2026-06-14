<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source_url',
    'external_id',
    'name',
    'rating',
    'ratings_count',
    'reviews_count',
    'sync_status',
    'sync_error',
    'synced_at',
])]
#[Hidden(['user_id'])]
class Organization extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function sourceUrl(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => rtrim($value, '/'),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
