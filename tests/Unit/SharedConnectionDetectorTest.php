<?php

namespace Tests\Unit;

use App\Services\SharedConnectionDetector;
use App\Services\Vtt\Cue;
use App\Services\Vtt\VttParser;
use PHPUnit\Framework\TestCase;

class SharedConnectionDetectorTest extends TestCase
{
    private function cue(string $speaker, string $text, int $start = 0, int $end = 1000): Cue
    {
        return new Cue($start, $end, $speaker, $text);
    }

    private function detect(Cue ...$cues): array
    {
        return (new SharedConnectionDetector)->detect($cues);
    }

    public function test_flags_speaker_addressing_themselves_by_name(): void
    {
        $flags = $this->detect($this->cue('Priya Patel', 'Thanks, Priya, that helps.'));

        $this->assertArrayHasKey(0, $flags);
    }

    public function test_does_not_flag_speaker_saying_their_own_name(): void
    {
        $this->assertSame([], $this->detect(
            $this->cue("Jordan's iPhone", "Yeah, sorry, it's Jordan. My laptop died."),
            $this->cue('Priya Patel', "Hi, I'm Priya, I'm in section two."),
        ));
    }

    public function test_flags_someone_else_introducing_themselves(): void
    {
        $flags = $this->detect(
            $this->cue('Priya Patel', "Yeah, sorry, it's Sam here, we're on one laptop."),
            $this->cue('Room 214 Zoom Room', 'This is Tom. Does that slow it down?'),
            $this->cue('Room 214 Zoom Room', "Grace, it's Aisha, are you coming back?"),
        );

        $this->assertSame([0, 1, 2], array_keys($flags));
        $this->assertStringContainsString('Sam', $flags[0]);
        $this->assertStringContainsString('Aisha', $flags[2]);
    }

    public function test_does_not_treat_days_as_names(): void
    {
        $this->assertSame([], $this->detect($this->cue('Dana Reyes', "It's Friday. Still due at midnight.")));
    }

    public function test_flags_quick_answer_to_own_question(): void
    {
        $flags = $this->detect(
            $this->cue('Priya Patel', 'Sam, didn\'t you look that up?', 0, 4000),
            $this->cue('Priya Patel', 'Yeah, I read that Python uses a power of two table.', 4500, 9000),
        );

        $this->assertSame([1], array_keys($flags));
    }

    public function test_slow_follow_up_to_own_question_is_not_flagged(): void
    {
        $this->assertSame([], $this->detect(
            $this->cue('Dana Reyes', 'Does that make sense?', 0, 2000),
            $this->cue('Dana Reyes', 'Yes, okay, moving on.', 9000, 11000),
        ));
    }

    public function test_flags_explicit_mention_of_sharing_a_device(): void
    {
        $this->assertArrayHasKey(0, $this->detect($this->cue('Marcus Lee', "We're sharing a laptop today, sorry.")));
    }

    public function test_ignores_unknown_speaker_lines(): void
    {
        $this->assertSame([], $this->detect($this->cue(VttParser::UNKNOWN_SPEAKER, "It's Leo, I'm calling in.")));
    }

    public function test_finds_the_shared_connections_in_the_sample_transcript(): void
    {
        $cues = (new VttParser)->parse(file_get_contents(__DIR__.'/../../public/sample-class.vtt'));
        $flagged = array_map(fn (int $i) => $cues[$i]->speaker, array_keys((new SharedConnectionDetector)->detect($cues)));

        $this->assertContains('Priya Patel', $flagged);
        $this->assertContains('Room 214 Zoom Room', $flagged);
        $this->assertNotContains('Dana Reyes', $flagged);
        $this->assertNotContains('Marcus Lee', $flagged);
    }
}
