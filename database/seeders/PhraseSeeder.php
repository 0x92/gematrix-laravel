<?php

namespace Database\Seeders;

use App\Models\Phrase;
use App\Services\GematriaCalculator;
use Illuminate\Database\Seeder;

class PhraseSeeder extends Seeder
{
    public function run(): void
    {
        $calculator = app(GematriaCalculator::class);

        $phrases = [
            'matrix code', 'hidden truth', 'cosmic signal', 'sacred geometry', 'quantum gate',
            'digital temple', 'prime pattern', 'silent observer', 'frequency field', 'future memory',
            'new world', 'alpha point', 'omega cycle', 'golden ratio', 'electric mind',
            'divine order', 'the system', 'open portal', 'coded reality', 'secret chamber',
            'star language', 'mind control', 'final chapter', 'arc of time', 'living numbers',
            'solar circuit', 'dark matter', 'inner signal', 'fractal dream', 'ancient key',
            'crystal grid', 'seven seals', 'lost archive', 'black cube', 'city of light',
            'eternal return', 'binary sun', 'emerald tablet', 'oracle stream', 'code breaker',
            'red pill', 'blue pill', 'great awakening', 'silent code', 'hidden hand',
            'grand design', 'deep state', 'new cycle', 'time vector', 'signal noise',
            'inner temple', 'world stage', 'truth network', 'machine spirit', 'open source',
            'digital alchemy', 'observer effect', 'master key', 'cipher gate', 'source field',
            'prime mover', 'arcane science', 'frequency code', 'higher self', 'infinite loop',
            'soul mirror', 'lunar tide', 'solar king', 'fire serpent', 'zero point',
            'silent thunder', 'velvet algorithm', 'radio ghost', 'memory palace', 'library of babel',
            'spectrum line', 'aurora engine', 'vector ritual', 'new horizon', 'cyber oracle',
            'stellar code', 'dream protocol', 'coded intention', 'future archive', 'delta sequence',
            'sky ledger', 'semantic pulse', 'clockwork mind', 'hidden geometry', 'signal garden',
            'quantum mirror', 'neural pattern', 'deep current', 'saturn ring', 'solar archive',
            'mystic archive', 'mirror lake', 'cathedral frequency', 'orange sunrise', 'atlas node',
        ];

        foreach ($phrases as $text) {
            $scores = $calculator->calculateAll($text);
            Phrase::query()->updateOrCreate(
                ['phrase' => $text],
                array_merge($scores, ['approved' => true])
            );
        }
    }
}
