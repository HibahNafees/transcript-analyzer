<?php

namespace App\Http\Controllers;

use App\Models\Transcript;
use App\Services\Fire\FireClient;
use App\Services\TranscriptAnalyzer;
use App\Services\TranscriptImporter;
use App\Services\Vtt\VttParseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TranscriptController extends Controller
{
    public function index(FireClient $fire): View
    {
        return view('transcripts.index', [
            'transcripts' => Transcript::withCount('speakers')->latest()->limit(50)->get(),
            'fireConfigured' => $fire->isConfigured(),
        ]);
    }

    public function store(Request $request, TranscriptImporter $importer, TranscriptAnalyzer $analyzer): RedirectResponse
    {
        $validated = $request->validate([
            // 4 MB: the Vercel proxy in front of the server rejects request bodies over ~4.5 MB.
            'transcript' => ['required', 'file', 'max:4096'],
            'title' => ['nullable', 'string', 'max:200'],
        ], [
            'transcript.max' => 'The transcript may not be larger than 4 MB.',
        ]);

        $file = $validated['transcript'];
        if (strtolower($file->getClientOriginalExtension()) !== 'vtt') {
            return back()->withErrors(['transcript' => 'Please upload a Zoom transcript (.vtt file).'])->withInput();
        }

        try {
            $transcript = $importer->import($file->get(), $file->getClientOriginalName(), $validated['title'] ?? null);
        } catch (VttParseException $e) {
            return back()->withErrors(['transcript' => $e->getMessage()])->withInput();
        }

        // Fire calls run in parallel, but a big class can still take a while.
        set_time_limit(180);
        $analyzer->analyze($transcript);

        return redirect()->route('transcripts.show', $transcript);
    }

    public function show(Transcript $transcript): View
    {
        $transcript->load([
            'speakers' => fn ($q) => $q->orderByDesc('word_count'),
            'utterances.speaker',
        ]);

        return view('transcripts.show', [
            'transcript' => $transcript,
            'totalWords' => max(1, $transcript->speakers->sum('word_count')),
            'flagged' => $transcript->utterances->where('shared_connection_flag', true),
        ]);
    }

    public function analyze(Transcript $transcript, TranscriptAnalyzer $analyzer): RedirectResponse
    {
        set_time_limit(180);
        $analyzer->analyze($transcript);

        return redirect()->route('transcripts.show', $transcript);
    }
}
