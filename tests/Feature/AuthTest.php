<?php

namespace Tests\Feature;

use App\Models\Transcript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const VTT = "WEBVTT\n\n1\n00:00:01.000 --> 00:00:03.000\nDana Reyes: Today is hash tables.\n";

    private function signup(array $overrides = []): TestResponse
    {
        return $this->post('/register', $overrides + [
            'invite_code' => 'class-2026',
            'name' => 'Hibah',
            'email' => 'hibah@example.com',
            'password' => 'correct horse',
            'password_confirmation' => 'correct horse',
        ]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->post('/transcripts', ['transcript' => UploadedFile::fake()->createWithContent('w.vtt', self::VTT)])
            ->assertRedirect(route('login'));

        $this->assertSame(0, Transcript::count());
    }

    public function test_register_with_invite_code_logs_in(): void
    {
        config(['auth.invite_code' => 'class-2026']);

        $this->signup()->assertRedirect(route('transcripts.index'));

        $this->assertAuthenticatedAs(User::sole());
    }

    public function test_register_rejects_wrong_invite_code(): void
    {
        config(['auth.invite_code' => 'class-2026']);

        $this->signup(['invite_code' => 'guess'])->assertSessionHasErrors('invite_code');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_registration_is_closed_without_an_invite_code(): void
    {
        config(['auth.invite_code' => null]);

        $this->get('/register')->assertOk()->assertSee('Registration is closed');
        $this->signup(['invite_code' => ''])->assertSessionHasErrors('invite_code');

        $this->assertSame(0, User::count());
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'correct horse']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'correct horse'])
            ->assertRedirect(route('transcripts.index'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_users_only_see_their_own_transcripts(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $mine = $owner->transcripts()->create(['title' => 'Owner week 5', 'original_filename' => 'w5.vtt']);

        $this->actingAs($other)->get('/')->assertOk()->assertDontSee('Owner week 5');
        $this->actingAs($other)->get(route('transcripts.show', $mine))->assertNotFound();
        $this->actingAs($other)->post(route('transcripts.analyze', $mine))->assertNotFound();

        $this->actingAs($owner)->get('/')->assertSee('Owner week 5');
        $this->actingAs($owner)->get(route('transcripts.show', $mine))->assertOk();
    }

    public function test_claim_command_assigns_unowned_transcripts(): void
    {
        $user = User::factory()->create(['email' => 'hibah@example.com']);
        $orphan = Transcript::create(['title' => 'Before accounts', 'original_filename' => 'old.vtt']);
        $owned = User::factory()->create()->transcripts()->create(['title' => 'Someone else', 'original_filename' => 'x.vtt']);

        $this->artisan('transcripts:claim', ['email' => 'hibah@example.com'])->assertSuccessful();

        $this->assertSame($user->id, $orphan->fresh()->user_id);
        $this->assertNotSame($user->id, $owned->fresh()->user_id);
    }
}
