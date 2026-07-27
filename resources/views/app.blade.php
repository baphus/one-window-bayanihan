<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php
            $siteName    = config('app.name', 'One Window Bayanihan');
            $description = config('app.description');
            // Absolute URLs are mandatory here. Facebook, Twitter/X, LinkedIn and
            // Slack all reject a relative og:image and then render no preview at
            // all, which is indistinguishable from having no tags.
            $socialImage = url(config('app.social_image', '/og-image.png'));
            $canonical   = url()->current();
            $indexable   = config('app.search_indexing_enabled');
        @endphp

        <title inertia>{{ $siteName }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">

        {{-- Mirrors the X-Robots-Tag header set by the SecurityHeaders middleware.
             Both are driven by the same config flag so they can never disagree;
             the header covers non-HTML responses, this covers crawlers that only
             parse markup. --}}
        <meta name="robots" content="{{ $indexable ? 'index, follow' : 'noindex, nofollow' }}">

        {{-- Open Graph: Facebook, LinkedIn, Slack, Viber, Messenger, WhatsApp --}}
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $siteName }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $socialImage }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $siteName }} — {{ config('app.owner') }}">
        <meta property="og:locale" content="en_PH">

        {{-- Twitter/X. summary_large_image is what produces the wide 1.91:1 card;
             the default `summary` crops to a small square and wastes the image. --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $siteName }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $socialImage }}">
        <meta name="twitter:image:alt" content="{{ $siteName }} — {{ config('app.owner') }}">

        <meta name="application-name" content="{{ $siteName }}">
        <meta name="theme-color" content="{{ config('app.theme_color', '#005288') }}">

        {{-- favicon.svg is preferred by modern browsers; favicon.ico remains the
             convention that crawlers and older clients request by default. --}}
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico" sizes="32x32">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Public+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

        <!-- Scripts -->
        @routes(nonce: $cspNonce ?? '')
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
