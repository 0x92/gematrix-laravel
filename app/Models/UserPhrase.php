<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPhrase extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'phrase', 'note', 'scores'];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
