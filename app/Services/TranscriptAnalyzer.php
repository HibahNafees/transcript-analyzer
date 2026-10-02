<?php

namespace App\Services;

use App\Models\Speaker;
use App\Models\Transcript;
use App\Services\Fire\FireClient;

/**
 * Asks Fire, once per speaker and in parallel, for a summary and one contribution.
 * Line and word counts are computed locally at import — never by the model.
 */
class TranscriptAnalyzer
{
    /** Keep each prompt well inside the model's context and Fire's max_tokens budget. */
    private const MAX_PROMPT_CHARS = 24000;

    private const SYSTEM_PROMPT = <<<'TXT'
You analyze one participant's speech from a class meeting transcript (Zoom auto-captions, so expect transcription errors).
Respond with ONLY a JSON object, no markdown, in exactly this shape:
{"summary": "<2-3 sentences: what this person talked about>", "contribution": "<one specific thing they contributed to the class: an idea, answer, question, or decision>"}
Be concrete and factual; use only what is in their lines. If they said very little, say so briefly.
TXT;

    public function __construct(private readonly FireClient $fire) {}

    public function analyze(Transcript $transcript): Transcript
    {
        $transcript->loadMissing('speakers.utterances');
        $speakers = $transcript->speakers->keyBy('id');

        $requests = $speakers->map(fn (Speaker $s) => [
            'system' => self::SYSTEM_PROMPT,
            'user' => $this->prompt($transcript, $s),
        ])->all();

        $results = $this->fire->chatMany($requests);

        $failures = 0;
        foreach ($results as $speakerId => $result) {
            $speaker = $speakers[$speakerId];
            $parsed = $result['ok'] ? $this->parseJson($result['content']) : null;

            if ($parsed === null) {
                $failures++;
                $speaker->update(['analysis_error' => $result['error'] ?? 'Fire response was not the expected JSON.']);

                continue;
            }

            $speaker->update([
                'summary' => $parsed['summary'],
                'contribution' => $parsed['contribution'],
                'analysis_error' => null,
            ]);
        }

        $total = count($results);
        $firstError = $speakers->first(fn (Speaker $s) => filled($s->analysis_error))?->analysis_error;

        $transcript->update([
            'status' => match (true) {
                $failures === 0 => 'analyzed',
                $failures < $total => 'partial',
                default => 'failed',
            },
            'model' => $this->fire->model(),
            'analysis_error' => $failures > 0 ? "{$failures} of {$total} speakers could not be analyzed. First error: {$firstError}" : null,
            'analyzed_at' => now(),
        ]);

        return $transcript;
    }

    private function prompt(Transcript $transcript, Speaker $speaker): string
    {
        $lines = $speaker->utterances
            ->map(fn ($u) => '['.gmdate('H:i:s', intdiv($u->start_ms, 1000)).'] '.$u->text)
            ->implode("\n");

        if (mb_strlen($lines) > self::MAX_PROMPT_CHARS) {
            $lines = mb_substr($lines, 0, self::MAX_PROMPT_CHARS)."\n[... truncated ...]";
        }

        return "Class meeting: {$transcript->title}\n"
            ."Participant label: {$speaker->name}\n"
            ."They spoke {$speaker->line_count} lines ({$speaker->word_count} words). Their lines:\n\n"
            .$lines;
    }

    /**
     * @return array{summary: string, contribution: string}|null
     */
    public function parseJson(string $content): ?array
    {
        // Models sometimes wrap JSON in code fences or add a sentence; take the outermost object.
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $data = json_decode(substr($content, $start, $end - $start + 1), true);
        if (! is_array($data) || ! is_string($data['summary'] ?? null) || ! is_string($data['contribution'] ?? null)) {
            return null;
        }

        return ['summary' => trim($data['summary']), 'contribution' => trim($data['contribution'])];
    }
}
