<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'preferred_ciphers',
        'theme',
        'locale_preference',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'preferred_ciphers' => 'array',
        ];
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SearchHistory::class);
    }

    public function userPhrases(): HasMany
    {
        return $this->hasMany(UserPhrase::class);
    }

    public function narratives(): HasMany
    {
        return $this->hasMany(ResearchNarrative::class);
    }
}
