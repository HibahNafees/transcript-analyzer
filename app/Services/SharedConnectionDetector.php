<?php

namespace App\Services;

use App\Services\Vtt\Cue;
use App\Services\Vtt\VttParser;

/**
 * Flags cues that were probably spoken by someone other than the labelled
 * speaker, i.e. two people sharing one Zoom connection.
 *
 * Heuristics, each cheap and explainable (the reason is shown to the user):
 *  1. self-address  — the speaker addresses themselves by first name ("Thanks, Priya")
 *  2. self-answer   — a speaker's question is answered by the same label within a few seconds
 *  3. introduction  — "this is Sam" / "Sam here" where Sam is not the labelled speaker
 *  4. explicit      — talk of sharing a laptop/computer/connection
 *
 * These are hints for a human to review, not ground truth.
 */
class SharedConnectionDetector
{
    private const SELF_ANSWER_GAP_MS = 3000;

    private const ANSWER_OPENERS = '/^(yes|yeah|yep|yup|no|nope|nah|sure|right|exactly|i think|i guess|um,? yeah|uh,? yeah|oh,? yeah|i mean|totally|definitely|agreed|i agree|good question)\b/i';

    /** Capitalized words that follow "it's" without being a person ("It's Friday."). */
    private const NOT_NAMES = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'today', 'tomorrow',
        'fine', 'okay', 'ok', 'true', 'false', 'right', 'done', 'me', 'python', 'java', 'linux', 'zoom', 'google',
    ];

    private const EXPLICIT_SHARING = '/\b(we\'?re (both|all) on (this|one|the same)|(sharing|share) (a|one|this|my|the same) (laptop|computer|account|screen|connection|zoom)|on (my|the same|one) (laptop|computer) (with|together)|(she|he|they)\'?s? (sitting )?(right )?next to me)\b/i';

    /**
     * @param  list<Cue>  $cues
     * @return array<int, string> cue index => human-readable reason
     */
    public function detect(array $cues): array
    {
        $flags = [];

        foreach ($cues as $i => $cue) {
            if ($cue->speaker === VttParser::UNKNOWN_SPEAKER) {
                continue;
            }

            $reason = $this->selfAddress($cue)
                ?? $this->introducesSomeoneElse($cue)
                ?? $this->explicitSharing($cue)
                ?? ($i > 0 ? $this->selfAnswer($cues[$i - 1], $cue) : null);

            if ($reason !== null) {
                $flags[$i] = $reason;
            }
        }

        return $flags;
    }

    private function selfAddress(Cue $cue): ?string
    {
        $first = $this->firstName($cue->speaker);
        if ($first === null) {
            return null;
        }
        $name = preg_quote($first, '/');

        // Self-references that are NOT a sign of a second person.
        if (preg_match("/\b(i'?m|i am|my name is|this is|it'?s)\s+{$name}\b/iu", $cue->text)) {
            return null;
        }

        $vocative = "/(^{$name}\s*[,?!]|\b(thanks|thank you|thx|sorry|hey|hi|right|okay|ok|yes|no|go ahead|over to you)\s*,?\s+{$name}\b|,\s*{$name}\s*[.?!]?$)/iu";

        return preg_match($vocative, $cue->text)
            ? "{$cue->speaker} appears to address \"{$first}\" — likely a second person on this connection"
            : null;
    }

    private function introducesSomeoneElse(Cue $cue): ?string
    {
        // "This is Tom." / "it's Sam here" / "Grace, it's Aisha" / "Sam here, ..." — a clause that is just a name.
        $pattern = "/(?:^|[,.!?]\s+|\b(?i:hi|hey|sorry|yeah)\W+)(?i:this is|it'?s)\s+(\p{Lu}\p{Ll}+)(?=\s*(?:[,.!?]|$|\s+(?:here|speaking|talking)\b))"
            ."|(?:^|[,.!?]\s+)(\p{Lu}\p{Ll}+) here\b(?=\s*[,.!—-])/u";
        if (! preg_match($pattern, $cue->text, $m)) {
            return null;
        }
        $introduced = ($m[1] ?? '') !== '' ? $m[1] : $m[2];
        $first = $this->firstName($cue->speaker);

        if ($first === null || strcasecmp($introduced, $first) === 0 || in_array(strtolower($introduced), self::NOT_NAMES, true)) {
            return null;
        }

        return "Someone introduces themselves as \"{$introduced}\" on {$cue->speaker}'s connection";
    }

    private function explicitSharing(Cue $cue): ?string
    {
        return preg_match(self::EXPLICIT_SHARING, $cue->text)
            ? 'Mentions sharing a device or connection'
            : null;
    }

    private function selfAnswer(Cue $previous, Cue $cue): ?string
    {
        $isQuestion = str_ends_with(rtrim($previous->text), '?');
        $quickReply = $cue->startMs - $previous->endMs <= self::SELF_ANSWER_GAP_MS;

        return $previous->speaker === $cue->speaker && $isQuestion && $quickReply
            && preg_match(self::ANSWER_OPENERS, ltrim($cue->text))
            ? "{$cue->speaker} answers their own question within seconds — possibly a second voice"
            : null;
    }

    private function firstName(string $speaker): ?string
    {
        // Zoom device names ("Jordan's iPhone") still start with the owner's name.
        $first = preg_split("/[\s']+/u", trim($speaker))[0] ?? '';

        return mb_strlen($first) >= 2 && preg_match('/^\p{L}+$/u', $first) ? $first : null;
    }
}
