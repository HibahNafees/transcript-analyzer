<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Utterance extends Model
{
    protected $fillable = [
        'transcript_id', 'speaker_id', 'sequence', 'cue_identifier', 'start_ms', 'end_ms',
        'text', 'word_count', 'shared_connection_flag', 'flag_reason',
    ];

    protected function casts(): array
    {
        return ['shared_connection_flag' => 'boolean'];
    }

    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}
