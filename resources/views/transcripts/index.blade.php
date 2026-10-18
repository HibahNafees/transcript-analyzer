@extends('layout')

@section('content')
    <h1>Analyze a class transcript</h1>
    <p class="muted">Upload a Zoom transcript (.vtt). You'll get each speaker's line and word counts, a summary of what they said, and one thing they contributed. Lines that look like two people sharing one connection get flagged.</p>

    @unless ($fireConfigured)
        <div class="notice warn">The Fire API key isn't configured, so uploads will only be parsed and counted, without summaries.</div>
    @endunless

    @if ($errors->any())
        <div class="notice err">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <form class="upload" method="POST" action="{{ route('transcripts.store') }}" enctype="multipart/form-data">
            @csrf
            <label>
                <span class="label">Transcript file (.vtt)</span>
                <input type="file" name="transcript" accept=".vtt,text/vtt" required>
            </label>
            <label>
                <span class="label">Title (optional)</span>
                <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. CS 101, Week 5 discussion" maxlength="200">
            </label>
            <button type="submit">Upload and analyze</button>
            <span class="muted small">Analysis takes about 10–30 seconds. No transcript handy? <a href="/sample-class.vtt" download>Download a sample</a>.</span>
        </form>
    </div>

    @if ($transcripts->isNotEmpty())
        <h2>Your transcripts</h2>
        <div class="card">
            <table>
                <thead><tr><th>Title</th><th class="num">Speakers</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($transcripts as $t)
                    <tr>
                        <td><a href="{{ route('transcripts.show', $t) }}">{{ $t->title }}</a><br><span class="muted small">{{ $t->created_at->format('M j, Y g:ia') }}</span></td>
                        <td class="num">{{ $t->speakers_count }}</td>
                        <td><span class="pill {{ $t->status }}">{{ $t->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
