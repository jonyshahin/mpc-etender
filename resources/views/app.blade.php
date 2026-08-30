<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'ku'], true) ? 'rtl' : 'ltr' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Transparent html so body::before (branded backdrop) shows through.
             The <body> has bg-background as a fallback paint before the image
             loads; the body::after veil handles readability contrast. --}}
        <style>
            html,
            html.dark {
                background-color: transparent;
            }
        </style>

        {{-- Both generated from public/mpc-logo.png: the .ico carries 16/32/48px
             rasters for the tab strip and bookmarks, the .svg the same mark at
             any size for browsers that prefer it. Serving the 2000x2000 source
             as the favicon cost 207KB on every page load. --}}
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/mpc-logo.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <script>
            window.__translations__ = @json(json_decode(file_get_contents(lang_path(app()->getLocale() . '.json')), true) ?? []);
            {{-- Display/input zone for every date the frontend renders. Storage
                 stays UTC; see config/mpc.php for why the two differ. --}}
            window.__timezone__ = @json(config('mpc.timezone'));
            {{-- POLICY-01 upload cap, so client-side checks and "up to :size"
                 hints match what the server will actually accept. --}}
            window.__maxUploadBytes__ = @json(\App\Rules\PdfFile::MAX_BYTES);
        </script>
        <x-inertia::app />
    </body>
</html>
