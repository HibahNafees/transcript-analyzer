<?php

namespace Tests\Feature;

use App\Models\Transcript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranscriptUploadTest extends TestCase
{
    use RefreshDatabase;

    private const VTT = "WEBVTT\n\n1\n00:00:01.000 --> 00:00:03.000\nDana Reyes: Today is hash tables.\n\n2\n00:00:04.000 --> 00:00:06.000\nMarcus Lee: Is the problem set due Friday?\n\n3\n00:00:07.000 --> 00:00:09.000\nDana Reyes: Yes, Friday at midnight.\n";

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.fire.key' => 'fire_sk_test', 'services.fire.base_url' => 'https://fire.test']);
    }

    public function test_upload_parses_stores_and_summarizes_each_speaker(): void
    {
        Http::fake(['fire.test/v1/chat' => Http::response([
            'content' => "```json\n{\"summary\": \"Talked about hash tables.\", \"contribution\": \"Set the topic.\"}\n```",
            'model' => 'claude-haiku-4-5',
        ])]);

        $response = $this->post('/transcripts', [
            'transcript' => UploadedFile::fake()->createWithContent('week5.vtt', self::VTT),
        ]);

        $transcript = Transcript::sole();
        $response->assertRedirect(route('transcripts.show', $transcript));
        $this->assertSame('analyzed', $transcript->status);

        $dana = $transcript->speakers()->where('name', 'Dana Reyes')->sole();
        $this->assertSame(2, $dana->line_count);
        $this->assertSame(8, $dana->word_count);
        $this->assertSame('Talked about hash tables.', $dana->summary);
        $this->assertSame(3, $transcript->utterances()->count());

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer fire_sk_test')
            && str_contains($request['messages'][0]['content'], 'Participant label:'));

        $this->get(route('transcripts.show', $transcript))
            ->assertOk()
            ->assertSee('Marcus Lee')
            ->assertSee('Talked about hash tables.');
    }

    public function test_fire_failure_keeps_counts_and_reports_the_error(): void
    {
        Http::fake(['fire.test/*' => Http::response(['error' => 'insufficient credit'], 402)]);

        $this->post('/transcripts', ['transcript' => UploadedFile::fake()->createWithContent('w.vtt', self::VTT)]);

        $transcript = Transcript::sole();
        $this->assertSame('failed', $transcript->status);
        $this->assertStringContainsString('insufficient credit', $transcript->analysis_error);
        $this->assertSame(1, $transcript->speakers()->where('name', 'Marcus Lee')->sole()->line_count);
    }

    public function test_rejects_non_vtt_upload(): void
    {
        $this->post('/transcripts', ['transcript' => UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
            ->assertSessionHasErrors('transcript');

        $this->assertSame(0, Transcript::count());
    }

    public function test_rejects_malformed_vtt(): void
    {
        $this->post('/transcripts', ['transcript' => UploadedFile::fake()->createWithContent('bad.vtt', 'not a transcript')])
            ->assertSessionHasErrors('transcript');

        $this->assertSame(0, Transcript::count());
    }
}
