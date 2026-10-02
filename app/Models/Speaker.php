<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Speaker extends Model
{
    protected $fillable = [
        'transcript_id', 'name', 'line_count', 'word_count', 'speaking_ms', 'summary', 'contribution', 'analysis_error',
    ];

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }

    public function utterances(): HasMany
    {
        return $this->hasMany(Utterance::class)->orderBy('sequence');
    }
}
