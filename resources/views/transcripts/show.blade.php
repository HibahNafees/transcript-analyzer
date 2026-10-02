@extends('layout')

@php
    $clock = fn (int $ms) => $ms >= 3600000 ? gmdate('G:i:s', intdiv($ms, 1000)) : gmdate('i:s', intdiv($ms, 1000));
@endphp

@section('title', $transcript->title.' · Transcript Analyzer')

@section('content')
    <h1>{{ $transcript->title }}</h1>
    <p class="muted small">
        {{ $transcript->original_filename }} · {{ $clock($transcript->duration_ms) }} long ·
        {{ $transcript->speakers->count() }} speakers · {{ $transcript->utterances->count() }} lines
        @if ($transcript->model) · summaries by {{ $transcript->model }} via Fire @endif
    </p>

    @if ($transcript->analysis_error)
        <div class="notice err">
            {{ $transcript->analysis_error }}
            <form method="POST" action="{{ route('transcripts.analyze', $transcript) }}" style="margin-top:8px">
                @csrf <button class="secondary" type="submit">Retry analysis</button>
            </form>
        </div>
    @endif

    @if ($flagged->isNotEmpty())
        <div class="notice warn">
            {{ $flagged->count() }} {{ Str::plural('line', $flagged->count()) }} may be two people on one connection.
            <a href="#flagged">Review them</a>.
        </div>
    @endif

    <h2>Speakers</h2>
    <div class="grid">
        @foreach ($transcript->speakers as $speaker)
            @php $share = round(100 * $speaker->word_count / $totalWords); @endphp
            <div class="card">
                <strong>{{ $speaker->name }}</strong>
                <div class="stats">
                    <span><b>{{ number_format($speaker->line_count) }}</b> lines</span>
                    <span><b>{{ number_format($speaker->word_count) }}</b> words</span>
                    <span><b>{{ $share }}%</b> of words</span>
                    <span><b>{{ $clock($speaker->speaking_ms) }}</b> talking</span>
                </div>
                <div class="bar" title="{{ $share }}% of all words"><span style="width: {{ $share }}%"></span></div>

                @if ($speaker->summary)
                    <div class="label">Summary</div>
                    <div>{{ $speaker->summary }}</div>
                    <div class="label">Contribution</div>
                    <div>{{ $speaker->contribution }}</div>
                @elseif ($speaker->analysis_error)
                    <div class="muted small">Not summarized: {{ $speaker->analysis_error }}</div>
                @else
                    <div class="muted small">Not summarized yet.</div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($flagged->isNotEmpty())
        <h2 id="flagged">Possibly two people on one connection</h2>
        <p class="muted small">Heuristic flags for a human to check: someone addressing the labelled speaker by name, answering their own question, or introducing themselves under someone else's label.</p>
        <div class="card">
            @foreach ($flagged as $u)
                <div class="line">
                    <span class="t">{{ $clock($u->start_ms) }}</span>
                    <div><span class="who">{{ $u->speaker->name }}</span>{{ $u->text }}<span class="flag-reason">{{ $u->flag_reason }}</span></div>
                </div>
            @endforeach
        </div>
    @endif

    <details>
        <summary>Full transcript ({{ $transcript->utterances->count() }} lines)</summary>
        <div class="card">
            @foreach ($transcript->utterances as $u)
                <div class="line {{ $u->shared_connection_flag ? 'flag' : '' }}">
                    <span class="t">{{ $clock($u->start_ms) }}</span>
                    <div><span class="who">{{ $u->speaker->name }}</span>{{ $u->text }}@if ($u->shared_connection_flag)<span class="flag-reason">{{ $u->flag_reason }}</span>@endif</div>
                </div>
            @endforeach
        </div>
    </details>
@endsection
