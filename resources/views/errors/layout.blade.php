{{--
    Shared shell for every error page. Self-contained (inline CSS, no Vite) so it
    renders even when the app itself is what failed.
--}}
@php
    $user = auth()->user();
    $home = url('/admin');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title') · Bulkstream</title>
    <style>
        :root {
            --canvas: #f4f4f5; --grid: rgba(24, 24, 27, .06); --surface: #fff; --line: #e4e4e7;
            --tick: #a1a1aa; --ink: #18181b; --muted: #71717a; --primary: #25348c; --primary-ink: #fff;
            --accent: @yield('accent', '#25348c');
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --canvas: #09090b; --grid: rgba(255, 255, 255, .05); --surface: #18181b; --line: #27272a;
                --tick: #52525b; --ink: #fafafa; --muted: #a1a1aa; --primary: #3446a8;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink);
            background-color: var(--canvas);
            background-image: linear-gradient(var(--grid) 1px, transparent 1px), linear-gradient(90deg, var(--grid) 1px, transparent 1px);
            background-size: 32px 32px; background-position: -1px -1px;
        }
        .card { position: relative; width: 100%; max-width: 560px; background: var(--surface); border: 1px solid var(--line); }
        .card::before, .card::after { content: ""; position: absolute; width: 12px; height: 12px; pointer-events: none; }
        .card::before { top: -1px; left: -1px; border-top: 2px solid var(--tick); border-left: 2px solid var(--tick); }
        .card::after { bottom: -1px; right: -1px; border-bottom: 2px solid var(--tick); border-right: 2px solid var(--tick); }
        header { display: flex; align-items: center; justify-content: space-between; padding: 18px 28px; border-bottom: 1px solid var(--line); }
        .wordmark { font-weight: 800; font-size: 1.35rem; letter-spacing: -.03em; color: var(--ink); text-decoration: none; }
        .wordmark span { color: var(--primary); }
        .code { font: 600 .75rem/1 ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .08em; color: var(--accent); border: 1px solid currentColor; padding: 4px 8px; }
        main { padding: 32px 28px 28px; }
        .gauge { display: flex; align-items: flex-end; gap: 6px; height: 56px; margin-bottom: 24px; }
        .gauge i { display: block; width: 14px; background: var(--line); }
        .gauge i.on { background: var(--accent); }
        h1 { margin: 0 0 10px; font-size: 1.6rem; letter-spacing: -.02em; line-height: 1.2; }
        p { margin: 0 0 12px; color: var(--muted); line-height: 1.55; }
        .who { margin: 18px 0 0; padding: 10px 12px; border-left: 3px solid var(--accent); background: color-mix(in srgb, var(--accent) 8%, transparent); color: var(--ink); font-size: .9rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 26px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 16px; font-weight: 600; font-size: .9rem; text-decoration: none; border: 1px solid var(--line); color: var(--ink); background: var(--surface); cursor: pointer; font-family: inherit; }
        .btn.primary { background: var(--primary); border-color: var(--primary); color: var(--primary-ink); }
        .btn:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        footer { padding: 14px 28px; border-top: 1px solid var(--line); font-size: .8rem; color: var(--muted); }
    </style>
</head>
<body>
    <div class="card" role="main">
        <header>
            <a class="wordmark" href="{{ $home }}">bulk<span>stream</span></a>
            <span class="code">@yield('code')</span>
        </header>
        <main>
            {{-- Tank-level motif: the number of lit bars reflects the error class. --}}
            <div class="gauge" aria-hidden="true">
                @foreach ([30, 55, 80, 45, 65, 25, 90, 50] as $i => $h)
                    <i class="{{ $i < (int) trim($__env->yieldContent('level', '3')) ? 'on' : '' }}" style="height: {{ $h }}%"></i>
                @endforeach
            </div>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            @yield('extra')
            <div class="actions">
                @hasSection('primary_action')
                    @yield('primary_action')
                @else
                    <a class="btn primary" href="{{ $home }}">Back to dashboard</a>
                @endif
                <button class="btn" type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ $home }}')">Go back</button>
            </div>
        </main>
        <footer>Bulkstream · Cargo Handling System @if ($user) · Signed in as {{ $user->name }} @endif</footer>
    </div>
</body>
</html>
