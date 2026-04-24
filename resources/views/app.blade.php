<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
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

        {{-- Preload hero backdrop variant (1920w). fetchpriority=low so it
             never competes with LCP content for the critical path. --}}
        <link rel="preload" as="image" href="/images/app-background-1920.webp" type="image/webp" fetchpriority="low" />

        <link rel="icon" href="/mpc-logo.png" type="image/png">
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
        </script>
        <x-inertia::app />
    </body>
</html>
