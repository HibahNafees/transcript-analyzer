<?php

namespace App\Services\Vtt;

/**
 * Parses Zoom-style WebVTT transcripts into speaker-attributed cues.
 *
 * Zoom writes the speaker as a "Name: " prefix on the cue text. Standard WebVTT
 * uses a <v Name> voice tag. Both are supported; a cue with neither gets
 * UNKNOWN_SPEAKER. Pure PHP, no framework dependencies, so it is unit-testable.
 */
class VttParser
{
    public const UNKNOWN_SPEAKER = 'Unknown speaker';

    /** Longest plausible display name before a colon; guards against "Note: ..." sentences. */
    private const MAX_SPEAKER_LENGTH = 60;

    private const MAX_SPEAKER_WORDS = 6;

    private const TIMING = '/^((?:\d+:)?\d{1,2}:\d{2}[.,]\d{1,3})\s+-->\s+((?:\d+:)?\d{1,2}:\d{2}[.,]\d{1,3})/';

    /**
     * @return list<Cue>
     *
     * @throws VttParseException when the input is not a WebVTT file or has no cues
     */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);

        if (! preg_match('/^WEBVTT(?:[ \t].*)?$/m', strtok(ltrim($contents), "\n") ?: '')) {
            throw new VttParseException('Not a WebVTT file: the first line must start with "WEBVTT".');
        }

        $cues = [];
        foreach (preg_split('/\n{2,}/', trim($contents)) as $block) {
            $cue = $this->parseBlock($block);
            if ($cue !== null) {
                $cues[] = $cue;
            }
        }

        if ($cues === []) {
            throw new VttParseException('The file contains no transcript cues.');
        }

        return $cues;
    }

    private function parseBlock(string $block): ?Cue
    {
        $lines = explode("\n", trim($block));

        // Header, NOTE, STYLE and REGION blocks carry no speech.
        if (preg_match('/^(WEBVTT|NOTE|STYLE|REGION)\b/', $lines[0])) {
            return null;
        }

        $identifier = null;
        if (! preg_match(self::TIMING, $lines[0])) {
            $identifier = array_shift($lines);
        }
        if ($lines === [] || ! preg_match(self::TIMING, $lines[0], $m)) {
            return null;
        }
        array_shift($lines);

        [$speaker, $text] = $this->splitSpeaker($lines);
        if ($text === '') {
            return null;
        }

        return new Cue(
            startMs: $this->toMilliseconds($m[1]),
            endMs: $this->toMilliseconds($m[2]),
            speaker: $speaker,
            text: $text,
            identifier: $identifier,
        );
    }

    /**
     * @param  list<string>  $lines  cue payload lines
     * @return array{0: string, 1: string} speaker and cleaned text
     */
    private function splitSpeaker(array $lines): array
    {
        $raw = trim(implode("\n", $lines));
        $speaker = null;

        // Standard WebVTT voice span: <v Name>text  or  <v.class Name>text
        if (preg_match('/^<v(?:\.[\w.-]+)?\s+([^>]+)>/u', $raw, $m)) {
            $speaker = trim($m[1]);
        }

        $text = $this->stripTags($raw);

        // Zoom style: "Name: text" on the first line of the cue.
        if ($speaker === null && preg_match('/^([^:\n]+?):\s+(.*)$/su', $text, $m) && $this->looksLikeSpeaker($m[1])) {
            $speaker = trim($m[1]);
            $text = $m[2];
        }

        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return [$speaker ?? self::UNKNOWN_SPEAKER, $text];
    }

    private function looksLikeSpeaker(string $candidate): bool
    {
        $candidate = trim($candidate);

        return $candidate !== ''
            && mb_strlen($candidate) <= self::MAX_SPEAKER_LENGTH
            && count(preg_split('/\s+/', $candidate)) <= self::MAX_SPEAKER_WORDS
            && ! str_contains(strtolower($candidate), 'http')
            && ! preg_match('/^\d+$/', $candidate)       // "10: ..." is a list item, not a person
            && ! preg_match('/[.?!]$/', $candidate);     // a sentence ending before a colon
    }

    private function stripTags(string $text): string
    {
        return html_entity_decode(preg_replace('/<[^>]*>/', '', $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function toMilliseconds(string $timestamp): int
    {
        $timestamp = str_replace(',', '.', $timestamp);
        [$clock, $fraction] = explode('.', $timestamp);
        $parts = array_map('intval', explode(':', $clock));
        if (count($parts) === 2) {
            array_unshift($parts, 0);
        }
        [$h, $m, $s] = $parts;

        return (($h * 3600 + $m * 60 + $s) * 1000) + (int) str_pad($fraction, 3, '0');
    }
}
