<?php

namespace App\Services;

class GematriaCalculator
{
    /**
     * @param array<int, string>|null $ciphers
     * @return array<string, int>
     */
    public function calculate(string $phrase, ?array $ciphers = null): array
    {
        $allScores = $this->calculateAll($phrase);

        if ($ciphers === null || $ciphers === []) {
            return $allScores;
        }

        $selected = [];
        foreach ($ciphers as $cipher) {
            if (array_key_exists($cipher, $allScores)) {
                $selected[$cipher] = $allScores[$cipher];
            }
        }

        return $selected;
    }

    /**
     * @return array<string, int>
     */
    public function calculateAll(string $phrase): array
    {
        $letters = $this->extractLetters($phrase);

        return [
            'english_gematria' => $this->sumMapped($letters, $this->englishMap()),
            'simple_gematria' => $this->sumMapped($letters, $this->simpleMap()),
            'unknown_gematria' => $this->sumMapped($letters, $this->unknownMap()),
            'pythagoras_gematria' => $this->sumMapped($letters, $this->pythagorasMap()),
            'jewish_gematria' => $this->sumMapped($letters, $this->jewishMap()),
            'prime_gematria' => $this->sumMapped($letters, $this->primeMap()),
            'reverse_satanic_gematria' => $this->sumMapped($letters, $this->reverseSatanicMap()),
            'clock_gematria' => $this->sumMapped($letters, $this->clockMap()),
            'reverse_clock_gematria' => $this->sumMapped($letters, $this->reverseClockMap()),
            'system9_gematria' => $this->sumMapped($letters, $this->system9Map()),
            'francis_bacon_gematria' => $this->sumMapped($letters, $this->francisBaconMap()),
            'septenary_gematria' => $this->sumMapped($letters, $this->septenaryMap()),
            'glyph_geometry_gematria' => $this->sumMapped($letters, $this->glyphGeometryMap()),

            // Legacy columns for backward compatibility with existing schema.
            'english_ordinal' => $this->sumMapped($letters, $this->simpleMap()),
            'reverse_ordinal' => $this->sumMapped($letters, $this->reverseOrdinalMap()),
            'full_reduction' => $this->sumMapped($letters, $this->reductionMap()),
            'reverse_reduction' => $this->sumMapped($letters, $this->reverseReductionMap()),
            'satanic' => $this->sumMapped($letters, $this->satanicMap()),
            'jewish' => $this->sumMapped($letters, $this->jewishMap()),
            'chaldean' => $this->sumMapped($letters, $this->chaldeanMap()),
            'primes' => $this->sumMapped($letters, $this->primeMap()),
            'trigonal' => $this->sumMapped($letters, $this->trigonalMap()),
            'squares' => $this->sumMapped($letters, $this->squaresMap()),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function extractLetterChars(string $text): array
    {
        $cleaned = strtoupper($text);
        $letters = [];

        foreach (str_split($cleaned) as $char) {
            $ord = ord($char);
            if ($ord < 65 || $ord > 90) {
                continue;
            }
            $letters[] = $char;
        }

        return $letters;
    }

    /**
     * @return array<int, int>
     */
    public function getCipherMap(string $cipher): array
    {
        return match ($cipher) {
            'english_gematria' => $this->englishMap(),
            'simple_gematria' => $this->simpleMap(),
            'unknown_gematria' => $this->unknownMap(),
            'pythagoras_gematria' => $this->pythagorasMap(),
            'jewish_gematria' => $this->jewishMap(),
            'prime_gematria' => $this->primeMap(),
            'reverse_satanic_gematria' => $this->reverseSatanicMap(),
            'clock_gematria' => $this->clockMap(),
            'reverse_clock_gematria' => $this->reverseClockMap(),
            'system9_gematria' => $this->system9Map(),
            'francis_bacon_gematria' => $this->francisBaconMap(),
            'septenary_gematria' => $this->septenaryMap(),
            'glyph_geometry_gematria' => $this->glyphGeometryMap(),
            default => [],
        };
    }

    /**
     * @return array<int, int>
     */
    private function extractLetters(string $text): array
    {
        $cleaned = strtoupper($text);
        $letters = [];

        foreach (str_split($cleaned) as $char) {
            $ord = ord($char);
            if ($ord < 65 || $ord > 90) {
                continue;
            }
            $letters[] = $ord - 64;
        }

        return $letters;
    }

    /**
     * @param array<int, int> $letters
     * @param array<int, int> $map
     */
    private function sumMapped(array $letters, array $map): int
    {
        return array_sum(array_map(static fn (int $value): int => $map[$value] ?? 0, $letters));
    }

    /** @return array<int, int> */
    private function englishMap(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = $i * 6;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function simpleMap(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = $i;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function unknownMap(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = $i + 98;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function pythagorasMap(): array
    {
        return [
            1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9,
            10 => 1, 11 => 11, 12 => 3, 13 => 4, 14 => 5, 15 => 6, 16 => 7, 17 => 8, 18 => 9,
            19 => 10, 20 => 2, 21 => 3, 22 => 22, 23 => 5, 24 => 6, 25 => 7, 26 => 8,
        ];
    }

    /** @return array<int, int> */
    private function jewishMap(): array
    {
        return [
            1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9,
            10 => 600, 11 => 10, 12 => 20, 13 => 30, 14 => 40, 15 => 50, 16 => 60, 17 => 70, 18 => 80, 19 => 90,
            20 => 100, 21 => 200, 22 => 700, 23 => 900, 24 => 300, 25 => 400, 26 => 500,
        ];
    }

    /** @return array<int, int> */
    private function primeMap(): array
    {
        return [
            1 => 2, 2 => 3, 3 => 5, 4 => 7, 5 => 11, 6 => 13, 7 => 17, 8 => 19, 9 => 23,
            10 => 29, 11 => 31, 12 => 37, 13 => 41, 14 => 43, 15 => 47, 16 => 53, 17 => 59, 18 => 61, 19 => 67,
            20 => 71, 21 => 73, 22 => 79, 23 => 83, 24 => 89, 25 => 97, 26 => 101,
        ];
    }

    /** @return array<int, int> */
    private function reverseSatanicMap(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = 62 - $i;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function clockMap(): array
    {
        return [
            1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9,
            10 => 10, 11 => 11, 12 => 12, 13 => 1, 14 => 2, 15 => 3, 16 => 4, 17 => 5, 18 => 6,
            19 => 7, 20 => 8, 21 => 9, 22 => 10, 23 => 11, 24 => 12, 25 => 1, 26 => 2,
        ];
    }

    /** @return array<int, int> */
    private function reverseClockMap(): array
    {
        return [
            1 => 2, 2 => 1, 3 => 12, 4 => 11, 5 => 10, 6 => 9, 7 => 8, 8 => 7, 9 => 6,
            10 => 5, 11 => 4, 12 => 3, 13 => 2, 14 => 1, 15 => 12, 16 => 11, 17 => 10, 18 => 9,
            19 => 8, 20 => 7, 21 => 6, 22 => 5, 23 => 4, 24 => 3, 25 => 2, 26 => 1,
        ];
    }

    /** @return array<int, int> */
    private function system9Map(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = $i * 9;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function francisBaconMap(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = $i + 26;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function septenaryMap(): array
    {
        return [
            1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 6, 9 => 5,
            10 => 4, 11 => 3, 12 => 2, 13 => 1, 14 => 1, 15 => 2, 16 => 3, 17 => 4, 18 => 5,
            19 => 6, 20 => 7, 21 => 6, 22 => 5, 23 => 4, 24 => 3, 25 => 2, 26 => 1,
        ];
    }

    /** @return array<int, int> */
    private function glyphGeometryMap(): array
    {
        $lines = [
            1 => 3, 2 => 1, 3 => 0, 4 => 1, 5 => 4, 6 => 3, 7 => 1, 8 => 3, 9 => 1,
            10 => 1, 11 => 3, 12 => 2, 13 => 4, 14 => 3, 15 => 0, 16 => 1, 17 => 1, 18 => 2,
            19 => 0, 20 => 2, 21 => 2, 22 => 2, 23 => 4, 24 => 2, 25 => 3, 26 => 3,
        ];
        $curves = [
            1 => 0, 2 => 2, 3 => 1, 4 => 1, 5 => 0, 6 => 0, 7 => 1, 8 => 0, 9 => 0,
            10 => 1, 11 => 0, 12 => 0, 13 => 0, 14 => 0, 15 => 1, 16 => 1, 17 => 1, 18 => 1,
            19 => 2, 20 => 0, 21 => 1, 22 => 0, 23 => 0, 24 => 0, 25 => 0, 26 => 0,
        ];
        $enclosed = [
            1 => 1, 2 => 2, 3 => 0, 4 => 1, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0,
            10 => 0, 11 => 0, 12 => 0, 13 => 0, 14 => 0, 15 => 1, 16 => 1, 17 => 1, 18 => 1,
            19 => 0, 20 => 0, 21 => 0, 22 => 0, 23 => 0, 24 => 0, 25 => 0, 26 => 0,
        ];

        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = ($lines[$i] * 2) + ($curves[$i] * 3) + ($enclosed[$i] * 5);
        }

        return $map;
    }

    /** @return array<int, int> */
    private function reverseOrdinalMap(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = 27 - $i;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function reductionMap(): array
    {
        return [
            1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9,
            10 => 1, 11 => 2, 12 => 3, 13 => 4, 14 => 5, 15 => 6, 16 => 7, 17 => 8, 18 => 9,
            19 => 1, 20 => 2, 21 => 3, 22 => 4, 23 => 5, 24 => 6, 25 => 7, 26 => 8,
        ];
    }

    /** @return array<int, int> */
    private function reverseReductionMap(): array
    {
        return [
            1 => 8, 2 => 7, 3 => 6, 4 => 5, 5 => 4, 6 => 3, 7 => 2, 8 => 1, 9 => 9,
            10 => 8, 11 => 7, 12 => 6, 13 => 5, 14 => 4, 15 => 3, 16 => 2, 17 => 1, 18 => 9,
            19 => 8, 20 => 7, 21 => 6, 22 => 5, 23 => 4, 24 => 3, 25 => 2, 26 => 1,
        ];
    }

    /** @return array<int, int> */
    private function satanicMap(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = $i + 35;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function chaldeanMap(): array
    {
        return [
            1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 8, 7 => 3, 8 => 5, 9 => 1,
            10 => 1, 11 => 2, 12 => 3, 13 => 4, 14 => 5, 15 => 7, 16 => 8, 17 => 1, 18 => 2,
            19 => 3, 20 => 4, 21 => 6, 22 => 6, 23 => 6, 24 => 5, 25 => 1, 26 => 7,
        ];
    }

    /** @return array<int, int> */
    private function trigonalMap(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = (int) (($i * ($i + 1)) / 2);
        }

        return $map;
    }

    /** @return array<int, int> */
    private function squaresMap(): array
    {
        $map = [];
        for ($i = 1; $i <= 26; $i++) {
            $map[$i] = $i * $i;
        }

        return $map;
    }
}
