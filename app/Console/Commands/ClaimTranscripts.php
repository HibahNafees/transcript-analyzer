<?php

namespace App\Console\Commands;

use App\Models\Transcript;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('transcripts:claim {email : The account that should own the unowned transcripts}')]
#[Description('Give transcripts uploaded before accounts existed to one account')]
class ClaimTranscripts extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account with that email. Register it first.');

            return self::FAILURE;
        }

        $count = Transcript::whereNull('user_id')->update(['user_id' => $user->id]);
        $this->info("Assigned {$count} transcript(s) to {$user->email}.");

        return self::SUCCESS;
    }
}
