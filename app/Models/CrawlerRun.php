<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlerRun extends Model
{
    protected $fillable = [
        'crawler_name',
        'status',
        'started_at',
        'finished_at',
        'current_item',
        'processed_count',
        'inserted_count',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
