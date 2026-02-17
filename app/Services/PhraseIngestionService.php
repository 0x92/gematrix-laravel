<?php

namespace App\Services;

use App\Models\Phrase;

class PhraseIngestionService
{
    public function __construct(private readonly GematriaCalculator $calculator)
    {
    }

    public function ensurePhrase(string $phrase): Phrase
    {
        $normalized = trim(mb_strtolower($phrase));

        if ($normalized === '') {
            throw new \InvalidArgumentException('Phrase is empty.');
        }

        $scores = $this->calculator->calculateAll($normalized);

        return Phrase::query()->updateOrCreate(
            ['phrase' => $normalized],
            array_merge($scores, ['approved' => true])
        );
    }

    public function ensurePhraseAndTokens(string $phrase): void
    {
        $this->ensurePhrase($phrase);

        $parts = preg_split('/\s+/', mb_strtolower(trim($phrase))) ?: [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2) {
                continue;
            }

            $clean = preg_replace('/[^a-z]/', '', $part) ?? '';
            if (mb_strlen($clean) < 2) {
                continue;
            }

            $this->ensurePhrase($clean);
        }
    }
}
