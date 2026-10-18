<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Transcript Analyzer')</title>
    <style>
        :root {
            --bg: #f6f5f1; --surface: #ffffff; --text: #1d1d1b; --muted: #6b6a64; --border: #e2e0d8;
            --accent: #2f5d8a; --accent-soft: #e4edf6; --warn: #8a5a00; --warn-soft: #fdf3dc; --err: #a33a2b; --err-soft: #fbe9e6;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #161615; --surface: #1f1f1d; --text: #ecebe6; --muted: #a09f98; --border: #34332f;
                --accent: #8db4dc; --accent-soft: #23303d; --warn: #e6b450; --warn-soft: #3a2f17; --err: #f08a7a; --err-soft: #3d221e;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; }
        a { color: var(--accent); }
        .wrap { max-width: 920px; margin: 0 auto; padding: 24px 16px 64px; }
        header.site { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 24px; }
        header.site a { text-decoration: none; color: var(--text); font-weight: 650; font-size: 1.1rem; }
        h1 { font-size: 1.6rem; line-height: 1.25; margin: 0 0 4px; }
        h2 { font-size: 1.15rem; margin: 32px 0 12px; }
        .muted { color: var(--muted); }
        .small { font-size: .875rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 16px 18px; margin-bottom: 12px; }
        .grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .stats { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: .9rem; color: var(--muted); margin: 4px 0 10px; }
        .stats b { color: var(--text); font-variant-numeric: tabular-nums; }
        .bar { height: 6px; background: var(--accent-soft); border-radius: 3px; overflow: hidden; margin-bottom: 12px; }
        .bar span { display: block; height: 100%; background: var(--accent); }
        .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin: 10px 0 2px; }
        .notice { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
        .notice.warn { background: var(--warn-soft); color: var(--warn); }
        .notice.err { background: var(--err-soft); color: var(--err); }
        form.upload { display: grid; gap: 12px; }
        input[type=text], input[type=email], input[type=password], input[type=file] { width: 100%; font: inherit; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text); }
        button { font: inherit; font-weight: 600; padding: 10px 16px; border: 0; border-radius: 6px; background: var(--accent); color: var(--surface); cursor: pointer; justify-self: start; }
        button.secondary { background: transparent; color: var(--accent); border: 1px solid var(--accent); padding: 6px 12px; font-size: .9rem; }
        table { width: 100%; border-collapse: collapse; }
        td, th { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { font-size: .8rem; color: var(--muted); font-weight: 600; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .line { display: grid; grid-template-columns: 64px 1fr; gap: 4px 12px; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .line .t { color: var(--muted); font-size: .8rem; font-variant-numeric: tabular-nums; padding-top: 3px; }
        .line .who { font-weight: 600; margin-right: 6px; }
        .line.flag { background: var(--warn-soft); margin: 0 -8px; padding: 8px; border-radius: 6px; }
        .flag-reason { display: block; font-size: .8rem; color: var(--warn); margin-top: 2px; }
        .pill { display: inline-block; font-size: .75rem; padding: 2px 8px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); white-space: nowrap; }
        .pill.failed, .pill.partial { background: var(--err-soft); color: var(--err); }
        form.auth { display: grid; gap: 12px; max-width: 420px; }
        .field-error { color: var(--err); font-size: .85rem; margin: 2px 0 0; }
        .who-bar { display: flex; align-items: center; gap: 10px; }
        .who-bar form { margin: 0; }
        details summary { cursor: pointer; font-weight: 600; margin: 24px 0 8px; }
        @media (max-width: 520px) { .line { grid-template-columns: 1fr; } h1 { font-size: 1.35rem; } }
    </style>
</head>
<body>
<div class="wrap">
    <header class="site">
        <a href="{{ route('transcripts.index') }}">Transcript Analyzer</a>
        @auth
            <span class="who-bar small">
                <span class="muted">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="secondary">Log out</button>
                </form>
            </span>
        @else
            <span class="muted small">Zoom .vtt → who said what</span>
        @endauth
    </header>
    @yield('content')
</div>
</body>
</html>
