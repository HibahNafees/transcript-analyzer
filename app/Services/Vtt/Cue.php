<?php

namespace App\Services\Vtt;

/**
 * One timed cue from a WebVTT file, with the speaker split out of the text.
 */
final class Cue
{
    public function __construct(
        public readonly int $startMs,
        public readonly int $endMs,
        public readonly string $speaker,
        public readonly string $text,
        public readonly ?string $identifier = null,
    ) {}

    public function wordCount(): int
    {
        return self::countWords($this->text);
    }

    public static function countWords(string $text): int
    {
        // Count runs of letters/digits (apostrophes and hyphens stay inside a word),
        // so "don't" and "well-known" are one word and stray punctuation is none.
        return preg_match_all("/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/u", $text);
    }
}
