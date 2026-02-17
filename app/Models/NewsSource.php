<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsSource extends Model
{
    protected $fillable = [
        'name',
        'base_url',
        'rss_url',
        'locale',
        'enabled',
        'weight',
        'last_checked_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function headlines(): HasMany
    {
        return $this->hasMany(NewsHeadline::class);
    }
}
