<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhraseClickEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'phrase_id',
        'user_id',
        'source',
        'search_query',
        'search_query_norm',
    ];

    public function phrase(): BelongsTo
    {
        return $this->belongsTo(Phrase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
