<?php

namespace Tests\Unit;

use App\Services\GematriaCalculator;
use PHPUnit\Framework\TestCase;

class GematriaCalculatorTest extends TestCase
{
    public function test_it_calculates_reference_ciphers(): void
    {
        $calculator = new GematriaCalculator();
        $scores = $calculator->calculateAll('matrix');

        $expected = [
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
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $scores);
        }

        $computer = $calculator->calculateAll('computer');
        $this->assertSame(666, $computer['english_gematria']);
        $this->assertSame(111, $computer['simple_gematria']);
        $this->assertSame(319, $computer['francis_bacon_gematria']);
        $this->assertSame(32, $computer['septenary_gematria']);
        $this->assertSame(60, $computer['glyph_geometry_gematria']);
        $this->assertGreaterThan(0, $scores['prime_gematria']);
    }
}
