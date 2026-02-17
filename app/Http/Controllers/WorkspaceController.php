<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\ResearchNarrative;
use App\Models\SearchHistory;
use App\Models\UserPhrase;
use App\Services\GematriaCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class WorkspaceController extends Controller
{
    public function __construct(private readonly GematriaCalculator $calculator)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('workspace.index', [
            'favorites' => $user->favorites()->latest()->take(50)->get(),
            'histories' => SearchHistory::query()->where('user_id', $user->id)->latest()->take(50)->get(),
            'userPhrases' => $user->userPhrases()->latest()->take(50)->get(),
            'narratives' => Schema::hasTable('research_narratives')
                ? $user->narratives()->latest()->take(20)->get()
                : collect(),
            'allCiphers' => config('gematria.ciphers'),
        ]);
    }

    public function favorite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:255'],
        ]);

        Favorite::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'phrase' => $validated['phrase']],
            ['scores' => $this->calculator->calculateAll($validated['phrase'])]
        );

        return back();
    }

    public function unfavorite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:255'],
        ]);

        Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('phrase', $validated['phrase'])
            ->delete();

        return back();
    }

    public function storePhrase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        UserPhrase::query()->create([
            'user_id' => $request->user()->id,
            'phrase' => $validated['phrase'],
            'note' => $validated['note'] ?? null,
            'scores' => $this->calculator->calculateAll($validated['phrase']),
        ]);

        return back();
    }

    public function removePhrase(UserPhrase $userPhrase, Request $request): RedirectResponse
    {
        abort_if($userPhrase->user_id !== $request->user()->id, 403);
        $userPhrase->delete();

        return back();
    }

    public function preferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferred_ciphers' => ['array'],
            'preferred_ciphers.*' => ['string'],
        ]);

        $request->user()->update([
            'theme' => 'dark',
            'locale_preference' => 'en',
            'preferred_ciphers' => $validated['preferred_ciphers'] ?? [],
        ]);

        return back();
    }

    public function storeNarrative(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('research_narratives')) {
            return back();
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'steps_raw' => ['nullable', 'string', 'max:8000'],
            'phrases_raw' => ['nullable', 'string', 'max:4000'],
        ]);

        $steps = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/u', (string) ($validated['steps_raw'] ?? '')) ?: []
        ), static fn (string $line): bool => $line !== ''));

        $linkedPhrases = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/u', (string) ($validated['phrases_raw'] ?? '')) ?: []
        ), static fn (string $line): bool => $line !== ''));

        ResearchNarrative::query()->create([
            'user_id' => (int) ($request->user()?->id ?? auth()->id()),
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'steps' => $steps,
            'linked_phrases' => $linkedPhrases,
        ]);

        return back();
    }

    public function showNarrative(ResearchNarrative $researchNarrative, Request $request): View
    {
        if (! Schema::hasTable('research_narratives')) {
            abort(404);
        }

        $currentUserId = $this->resolveCurrentUserId($request);
        abort_if($currentUserId === 0 || $researchNarrative->user_id !== $currentUserId, 403);
        $researchNarrative->increment('view_count');
        $linked = collect($researchNarrative->linked_phrases ?? [])
            ->map(static fn ($phrase): string => trim((string) $phrase))
            ->filter(static fn (string $phrase): bool => $phrase !== '')
            ->values();

        $phraseEntries = $linked->isEmpty()
            ? collect()
            : UserPhrase::query()
                ->where('user_id', $currentUserId)
                ->whereIn('phrase', $linked->all())
                ->latest()
                ->get();

        return view('workspace.narrative', [
            'narrative' => $researchNarrative->fresh(),
            'phraseEntries' => $phraseEntries,
        ]);
    }

    public function removeNarrative(ResearchNarrative $researchNarrative, Request $request): RedirectResponse
    {
        if (! Schema::hasTable('research_narratives')) {
            return back();
        }

        $currentUserId = (int) ($request->user()?->id ?? auth()->id() ?? 0);
        abort_if($currentUserId === 0 || $researchNarrative->user_id !== $currentUserId, 403);
        $researchNarrative->delete();

        return back();
    }

    public function exportNarrativeMarkdown(ResearchNarrative $researchNarrative, Request $request): Response
    {
        if (! Schema::hasTable('research_narratives')) {
            abort(404);
        }
        $currentUserId = $this->resolveCurrentUserId($request);
        abort_if($currentUserId === 0 || $researchNarrative->user_id !== $currentUserId, 403);

        $lines = [];
        $lines[] = '# '.$researchNarrative->title;
        $lines[] = '';
        if ($researchNarrative->summary) {
            $lines[] = $researchNarrative->summary;
            $lines[] = '';
        }

        $lines[] = '## Steps';
        foreach (($researchNarrative->steps ?? []) as $idx => $step) {
            $lines[] = ($idx + 1).'. '.trim((string) $step);
        }
        if (($researchNarrative->steps ?? []) === []) {
            $lines[] = '- No documented steps.';
        }
        $lines[] = '';
        $lines[] = '## Linked Phrases';
        foreach (($researchNarrative->linked_phrases ?? []) as $phrase) {
            $safePhrase = trim((string) $phrase);
            if ($safePhrase !== '') {
                $lines[] = '- '.$safePhrase;
            }
        }
        if (($researchNarrative->linked_phrases ?? []) === []) {
            $lines[] = '- No linked phrases.';
        }
        $lines[] = '';
        $lines[] = 'Generated by Gematrix on '.now()->toDateTimeString().' UTC.';
        $content = implode("\n", $lines);

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="narrative-'.$researchNarrative->id.'.md"',
        ]);
    }

    public function exportNarrativePrint(ResearchNarrative $researchNarrative, Request $request): View
    {
        if (! Schema::hasTable('research_narratives')) {
            abort(404);
        }
        $currentUserId = $this->resolveCurrentUserId($request);
        abort_if($currentUserId === 0 || $researchNarrative->user_id !== $currentUserId, 403);

        return view('workspace.narrative_print', [
            'narrative' => $researchNarrative,
        ]);
    }

    private function resolveCurrentUserId(Request $request): int
    {
        return (int) ($request->user()?->id ?? auth()->id() ?? 0);
    }
}
