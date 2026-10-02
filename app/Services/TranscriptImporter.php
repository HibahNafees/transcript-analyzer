<?php

namespace App\Services;

use App\Models\Transcript;
use App\Services\Vtt\Cue;
use App\Services\Vtt\VttParser;
use Illuminate\Support\Facades\DB;

/**
 * Parses a .vtt file and stores the transcript, its speakers and every line in Postgres.
 */
class TranscriptImporter
{
    public function __construct(
        private readonly VttParser $parser,
        private readonly SharedConnectionDetector $detector,
    ) {}

    /**
     * @throws Vtt\VttParseException
     */
    public function import(string $contents, string $filename, ?string $title = null): Transcript
    {
        $cues = $this->parser->parse($contents);
        $flags = $this->detector->detect($cues);

        return DB::transaction(function () use ($cues, $flags, $filename, $title) {
            $transcript = Transcript::create([
                'title' => filled($title) ? $title : pathinfo($filename, PATHINFO_FILENAME),
                'original_filename' => $filename,
                'status' => 'parsed',
                'duration_ms' => max(array_map(fn (Cue $c) => $c->endMs, $cues)),
            ]);

            $speakers = [];
            foreach ($cues as $cue) {
                $stats = $speakers[$cue->speaker] ??= ['line_count' => 0, 'word_count' => 0, 'speaking_ms' => 0];
                $speakers[$cue->speaker] = [
                    'line_count' => $stats['line_count'] + 1,
                    'word_count' => $stats['word_count'] + $cue->wordCount(),
                    'speaking_ms' => $stats['speaking_ms'] + max(0, $cue->endMs - $cue->startMs),
                ];
            }

            $speakerIds = [];
            foreach ($speakers as $name => $stats) {
                $speakerIds[$name] = $transcript->speakers()->create(['name' => $name] + $stats)->id;
            }

            $now = now();
            $rows = [];
            foreach ($cues as $i => $cue) {
                $rows[] = [
                    'transcript_id' => $transcript->id,
                    'speaker_id' => $speakerIds[$cue->speaker],
                    'sequence' => $i + 1,
                    'cue_identifier' => $cue->identifier,
                    'start_ms' => $cue->startMs,
                    'end_ms' => $cue->endMs,
                    'text' => $cue->text,
                    'word_count' => $cue->wordCount(),
                    'shared_connection_flag' => isset($flags[$i]),
                    'flag_reason' => $flags[$i] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('utterances')->insert($chunk);
            }

            return $transcript;
        });
    }
}
