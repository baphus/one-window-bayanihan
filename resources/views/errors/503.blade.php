<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Under Maintenance - One Window Bayanihan</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Public+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        serif: ['Outfit', 'sans-serif'],
                        sans: ['Public Sans', 'sans-serif'],
                    },
                    colors: {
                        primary: '#005288',
                    },
                },
            },
        }
    </script>

    <style>
        body { font-family: 'Public Sans', sans-serif; }
    </style>
</head>
<body class="font-sans antialiased bg-white min-h-dvh flex items-center justify-center p-10">
    <div class="w-full max-w-md text-center">
        <span class="material-symbols-outlined mb-4 block text-primary text-[48px]">
            build
        </span>

        <h1 class="font-serif text-2xl font-bold text-slate-900 mb-2">
            Under Maintenance
        </h1>

        <p class="text-6xl font-bold text-slate-200 mb-6 select-none">503</p>

        <p class="text-sm text-slate-500 leading-relaxed mb-6">
            We're performing scheduled maintenance to improve the system.
            Please try again in a few minutes.
        </p>

        @if(isset($retry) && $retry)
            <div class="inline-flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 mb-6">
                <span class="material-symbols-outlined text-[18px] text-primary">schedule</span>
                <span class="text-sm text-slate-600">
                    Estimated time: approximately <strong>{{ $retry }}</strong> minute{{ $retry > 1 ? 's' : '' }}
                </span>
            </div>
        @endif

        <button
            onclick="window.location.reload()"
            class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white text-sm font-bold rounded-lg hover:bg-[#003d66] transition-colors"
        >
            <span class="material-symbols-outlined text-[18px]">refresh</span>
            Try Again
        </button>

        <p class="mt-8 text-xs text-slate-400">&copy; {{ date('Y') }} One Window Bayanihan. All rights reserved.</p>
    </div>
</body>
</html>
