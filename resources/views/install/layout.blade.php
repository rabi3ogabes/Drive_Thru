@php
    $rtl = app()->getLocale() === 'ar';
    $other = $rtl ? 'en' : 'ar';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('install.title') }}</title>
    @if (is_file(public_path('build/manifest.json')))
        @vite('resources/css/app.css')
    @endif
    <style>
        /* Mirrors resources/css/tokens.css: the installer must look right before the assets are built. */
        :root {
            --ink-950: #0a0f1c; --ink-700: #2c3445; --ink-500: #5d6575; --ink-300: #a9aebb; --ink-100: #e7e8ec;
            --ivory-50: #fcfbf8; --ivory-100: #f6f3ec; --white: #ffffff;
            --teal-800: #143f3b; --teal-700: #1d524c; --teal-50: #eef5f3;
            --brass-400: #c39e66; --brass-100: #f2e8d6;
            --danger-600: #b42318; --danger-50: #fef3f2; --success-700: #1f6b46; --success-50: #edf7f1;
            --surface: var(--white); --page: var(--ivory-50); --text: var(--ink-950); --muted: var(--ink-500); --line: color-mix(in oklab, var(--ink-950) 9%, transparent);
            --ease: cubic-bezier(0.32, 0.72, 0, 1);
            color-scheme: light;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --surface: #121826; --page: #0a0f1c; --text: #f4f1ea; --muted: #a9aebb; --line: color-mix(in oklab, white 10%, transparent);
                --teal-50: #12302d; --success-50: #10261b; --danger-50: #2a1414; --brass-100: #2a2217;
                color-scheme: dark;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; background: var(--page); color: var(--text);
            font-family: 'Geist Variable', 'IBM Plex Sans Arabic', ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        [dir=rtl] body { font-family: 'IBM Plex Sans Arabic', 'Geist Variable', ui-sans-serif, system-ui, sans-serif; }
        .glow { position: fixed; inset-inline-end: -10rem; inset-block-start: -12rem; width: 34rem; height: 34rem; border-radius: 50%;
            background: radial-gradient(closest-side, color-mix(in oklab, var(--brass-400) 22%, transparent), transparent); pointer-events: none; }
        .wrap { position: relative; max-width: 44rem; margin: 0 auto; padding: max(1.5rem, env(safe-area-inset-top)) 1rem 4rem; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .brand { display: flex; align-items: center; gap: .625rem; font-weight: 600; letter-spacing: -.01em; }
        .mark { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: .8rem; background: var(--teal-800); color: var(--white); }
        .pill { border-radius: 999px; padding: .45rem .9rem; font-size: .8125rem; font-weight: 500; color: var(--text); text-decoration: none; border: 1px solid var(--line); background: var(--surface); }
        .eyebrow { display: inline-block; margin-top: 3rem; border-radius: 999px; padding: .3rem .75rem; font-size: .6875rem; font-weight: 600; letter-spacing: .16em; text-transform: uppercase;
            background: var(--brass-100); color: var(--text); }
        [dir=rtl] .eyebrow { letter-spacing: 0; }
        h1 { margin: 1rem 0 0; font-family: 'Fraunces Variable', 'Alexandria Variable', ui-serif, Georgia, serif; font-weight: 500; font-size: clamp(2rem, 6vw, 2.75rem); line-height: 1.08; letter-spacing: -.02em; }
        [dir=rtl] h1 { font-family: 'Alexandria Variable', 'IBM Plex Sans Arabic', ui-sans-serif, sans-serif; letter-spacing: 0; line-height: 1.25; }
        .lead { margin: .75rem 0 0; color: var(--muted); line-height: 1.6; max-width: 36rem; }
        .shell { margin-top: 1.25rem; border-radius: 1.75rem; padding: .375rem; background: color-mix(in oklab, var(--text) 4%, transparent); border: 1px solid var(--line); }
        .core { border-radius: calc(1.75rem - .375rem); background: var(--surface); padding: 1.25rem; box-shadow: inset 0 1px 0 color-mix(in oklab, white 40%, transparent); }
        @media (min-width: 640px) { .core { padding: 1.75rem; } }
        .step { display: flex; align-items: center; gap: .75rem; margin: 0; font-size: 1.0625rem; font-weight: 600; }
        .num { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: var(--teal-800); color: var(--white); font-size: .8125rem; font-variant-numeric: tabular-nums; }
        .hint { margin: .5rem 0 0; color: var(--muted); font-size: .875rem; line-height: 1.55; }
        .grid { display: grid; gap: 1rem; margin-top: 1.25rem; }
        @media (min-width: 640px) { .grid.two { grid-template-columns: 1fr 1fr; } .span { grid-column: 1 / -1; } }
        label { display: block; font-size: .8125rem; font-weight: 500; margin-bottom: .4rem; }
        input { width: 100%; border-radius: 1rem; border: 1px solid var(--line); background: var(--page); color: var(--text); padding: .8rem 1rem; font: inherit; font-size: 1rem;
            transition: border-color .2s var(--ease), box-shadow .2s var(--ease); }
        input:focus { outline: none; border-color: var(--teal-700); box-shadow: 0 0 0 4px color-mix(in oklab, var(--teal-700) 18%, transparent); }
        input[aria-invalid=true] { border-color: var(--danger-600); }
        .ltr { direction: ltr; text-align: start; }
        [dir=rtl] .ltr { text-align: end; }
        .err { margin: .4rem 0 0; color: var(--danger-600); font-size: .8125rem; }
        .field-hint { margin: .4rem 0 0; color: var(--muted); font-size: .75rem; }
        .alert { margin-top: 1.25rem; border-radius: 1rem; padding: .9rem 1rem; font-size: .9rem; line-height: 1.5; }
        .alert.bad { background: var(--danger-50); color: var(--danger-600); }
        .alert.good { background: var(--success-50); color: var(--success-700); }
        .checks { list-style: none; margin: 1rem 0 0; padding: 0; display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); }
        .checks li { display: flex; align-items: center; gap: .5rem; font-size: .8125rem; border-radius: .75rem; padding: .5rem .65rem; background: var(--page); border: 1px solid var(--line); }
        .checks li.no { color: var(--danger-600); border-color: color-mix(in oklab, var(--danger-600) 35%, transparent); background: var(--danger-50); }
        .dot { width: .5rem; height: .5rem; border-radius: 50%; background: var(--success-700); flex: none; }
        .no .dot { background: var(--danger-600); }
        code, .file { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8125rem; }
        .file { display: block; margin-top: .75rem; direction: ltr; text-align: left; border-radius: .75rem; padding: .65rem .8rem; background: var(--page); border: 1px dashed var(--line); overflow-wrap: anywhere; }
        details summary { cursor: pointer; color: var(--muted); font-size: .8125rem; margin-top: .75rem; }
        .cta { display: inline-flex; align-items: center; justify-content: center; gap: .75rem; width: 100%; margin-top: 1.5rem; border: 0; border-radius: 999px; padding: .5rem .5rem .5rem 1.5rem;
            background: var(--ink-950); color: var(--white); font: inherit; font-weight: 600; font-size: 1rem; cursor: pointer; text-decoration: none; min-height: 3.25rem;
            transition: transform .2s var(--ease), opacity .2s var(--ease); }
        [dir=rtl] .cta { padding: .5rem 1.5rem .5rem .5rem; }
        .cta:active { transform: scale(.98); }
        .cta[disabled] { opacity: .55; cursor: not-allowed; }
        .cta .ic { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: 50%; background: color-mix(in oklab, white 14%, transparent); margin-inline-start: auto; transition: transform .3s var(--ease); }
        .cta:hover .ic { transform: translateX(2px); }
        [dir=rtl] .cta .ic svg { transform: scaleX(-1); }
        .spin { width: 1.1rem; height: 1.1rem; border-radius: 50%; border: 2px solid color-mix(in oklab, white 30%, transparent); border-top-color: var(--white); animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        .rise { animation: rise .7s var(--ease) both; }
        @media (prefers-reduced-motion: reduce) { .rise, .spin { animation: none; } }
        .divider { height: 1px; background: var(--line); margin: 1.75rem 0; border: 0; }
        ol.next { margin: .75rem 0 0; padding-inline-start: 1.25rem; color: var(--muted); line-height: 1.7; font-size: .9rem; }
    </style>
</head>
<body>
    <div class="glow" aria-hidden="true"></div>
    <div class="wrap">
        <header class="top">
            <span class="brand">
                <span class="mark" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16V9a2 2 0 0 1 2-2h7l4 4h1a2 2 0 0 1 2 2v3"/><circle cx="7.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/></svg>
                </span>
                Drive-Thru Plus
            </span>
            <a class="pill" href="{{ url('/install') }}?lang={{ $other }}" lang="{{ $other }}">{{ __('install.language') }}</a>
        </header>
        <main>
            @yield('content')
        </main>
    </div>
</body>
</html>
