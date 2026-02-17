<?php

namespace App\Services;

class NumberWordNormalizer
{
    public function normalizeHeadline(string $headline): string
    {
        $utf8 = @iconv('UTF-8', 'UTF-8//IGNORE', $headline);
        if (is_string($utf8) && $utf8 !== '') {
            $headline = $utf8;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $headline) ?? '');
        if ($text === '') {
            return '';
        }

        $text = preg_replace_callback('/\b(\d{1,3}(?:,\d{3})*|\d+)\.(\d+)\b/u', function (array $m): string {
            $wholeRaw = str_replace(',', '', (string) $m[1]);
            $fractionRaw = (string) $m[2];
            if (! ctype_digit($wholeRaw) || ! ctype_digit($fractionRaw)) {
                return (string) $m[0];
            }

            return $this->decimalToWords((int) $wholeRaw, $fractionRaw);
        }, $text) ?? $text;

        $text = preg_replace_callback('/\b(\d{1,3}(?:,\d{3})*|\d{1,12})(st|nd|rd|th)\b/ui', function (array $m): string {
            $raw = str_replace(',', '', (string) $m[1]);
            if (! ctype_digit($raw)) {
                return (string) $m[0];
            }

            $n = (int) $raw;
            return $this->ordinalToWords($n);
        }, $text) ?? $text;

        $text = preg_replace_callback('/\b(\d{1,3}(?:,\d{3})*|\d{1,12})\b/u', function (array $m): string {
            $raw = str_replace(',', '', (string) $m[1]);
            if (! ctype_digit($raw)) {
                return (string) $m[0];
            }

            $n = (int) $raw;
            return $this->numberToWords($n);
        }, $text) ?? $text;

        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''));

        return $text;
    }

    private function numberToWords(int $n): string
    {
        if ($n === 0) {
            return 'zero';
        }
        if ($n < 0) {
            return 'minus '.$this->numberToWords(abs($n));
        }

        $units = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
        $teens = [10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        $parts = [];
        $scales = [
            1_000_000_000 => 'billion',
            1_000_000 => 'million',
            1000 => 'thousand',
        ];

        foreach ($scales as $scale => $label) {
            if ($n >= $scale) {
                $count = intdiv($n, $scale);
                $n %= $scale;
                $parts[] = $this->underThousand($count, $units, $teens, $tens).' '.$label;
            }
        }

        if ($n > 0) {
            $parts[] = $this->underThousand($n, $units, $teens, $tens);
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param array<int, string> $units
     * @param array<int, string> $teens
     * @param array<int, string> $tens
     */
    private function underThousand(int $n, array $units, array $teens, array $tens): string
    {
        $parts = [];

        if ($n >= 100) {
            $parts[] = $units[intdiv($n, 100)].' hundred';
            $n %= 100;
        }

        if ($n >= 20) {
            $parts[] = $tens[intdiv($n, 10)].($n % 10 > 0 ? '-'.$units[$n % 10] : '');
        } elseif ($n >= 10) {
            $parts[] = $teens[$n];
        } elseif ($n > 0) {
            $parts[] = $units[$n];
        }

        return implode(' ', $parts);
    }

    private function ordinalToWords(int $n): string
    {
        $base = $this->numberToWords($n);
        if ($base === '') {
            return '';
        }

        $parts = explode(' ', $base);
        $last = array_pop($parts);
        if ($last === null) {
            return $base;
        }

        $parts[] = $this->toOrdinalToken($last);

        return implode(' ', $parts);
    }

    private function toOrdinalToken(string $token): string
    {
        $pieces = explode('-', $token);
        $last = array_pop($pieces);
        if ($last === null) {
            return $token;
        }

        $irregular = [
            'one' => 'first',
            'two' => 'second',
            'three' => 'third',
            'five' => 'fifth',
            'eight' => 'eighth',
            'nine' => 'ninth',
            'twelve' => 'twelfth',
        ];

        $ord = $irregular[$last] ?? null;
        if ($ord === null) {
            if (str_ends_with($last, 'y')) {
                $ord = substr($last, 0, -1).'ieth';
            } elseif (str_ends_with($last, 'e')) {
                $ord = $last.'th';
            } else {
                $ord = $last.'th';
            }
        }

        $pieces[] = $ord;

        return implode('-', $pieces);
    }

    private function decimalToWords(int $whole, string $fractionDigits): string
    {
        $digitWords = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
        $fraction = [];
        foreach (str_split($fractionDigits) as $digit) {
            $fraction[] = $digitWords[(int) $digit] ?? 'zero';
        }

        return trim($this->numberToWords($whole).' point '.implode(' ', $fraction));
    }
}
