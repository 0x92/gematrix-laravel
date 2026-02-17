<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Phrase extends Model
{
    use HasFactory;

    protected $fillable = [
        'phrase',
        'english_gematria',
        'simple_gematria',
        'unknown_gematria',
        'pythagoras_gematria',
        'jewish_gematria',
        'prime_gematria',
        'reverse_satanic_gematria',
        'clock_gematria',
        'reverse_clock_gematria',
        'system9_gematria',
        'francis_bacon_gematria',
        'septenary_gematria',
        'glyph_geometry_gematria',
        'english_ordinal',
        'reverse_ordinal',
        'full_reduction',
        'reverse_reduction',
        'satanic',
        'jewish',
        'chaldean',
        'primes',
        'trigonal',
        'squares',
        'view_count',
        'approved',
    ];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
        ];
    }
}
