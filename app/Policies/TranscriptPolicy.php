<?php

namespace App\Policies;

use App\Models\Transcript;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TranscriptPolicy
{
    /**
     * Transcripts are private to their uploader. Other users get a 404, so they can't
     * tell which transcript ids exist.
     */
    public function view(User $user, Transcript $transcript): Response
    {
        return $user->id === $transcript->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function analyze(User $user, Transcript $transcript): Response
    {
        return $this->view($user, $transcript);
    }
}
