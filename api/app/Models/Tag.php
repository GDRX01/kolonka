<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING = 'pending';   // suggested by a user, waits for a moderator

    /** How many not-yet-approved tags one ordinary user may have at the same time (anti-spam). */
    public const MAX_PENDING_PER_USER = 15;

    protected $fillable = ['name', 'slug', 'status', 'created_by'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('tags.status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** "  #Laravel  " -> "laravel" */
    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = ltrim($name, '#');

        return trim(preg_replace('/\s+/u', ' ', $name));
    }
}
