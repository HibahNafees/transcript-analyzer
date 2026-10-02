<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transcript extends Model
{
    protected $fillable = [
        'title', 'original_filename', 'status', 'model', 'analysis_error', 'duration_ms', 'analyzed_at',
    ];

    protected function casts(): array
    {
        return ['analyzed_at' => 'datetime'];
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(Speaker::class);
    }

    public function utterances(): HasMany
    {
        return $this->hasMany(Utterance::class)->orderBy('sequence');
    }
}
