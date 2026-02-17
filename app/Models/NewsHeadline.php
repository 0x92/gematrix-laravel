<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsHeadline extends Model
{
    protected $fillable = [
        'news_source_id',
        'headline',
        'headline_normalized',
        'url',
        'url_hash',
        'published_at',
        'headline_date',
        'locale',
        'english_gematria',
        'scores',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'headline_date' => 'date',
            'scores' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'news_source_id');
    }
}
