<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Under Maintenance - One Window Bayanihan</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        :root {
            --sky: #00a3f0;
            --sky-deep: #0a5fb4;
            --sky-soft: #aee0fc;
            --mint: #22e05c;
            --mint-soft: #b9f3cd;
            --amber: #f5b83d;
            --amber-deep: #e2962a;
            --steel: #4a4e55;
            --cycle: 10s;
        }

        body {
            font-family: 'Public Sans', sans-serif;
            background: #ffffff;
        }

        /* ---------- Crane animation ---------- */
        .crane-svg { overflow: visible; }

        .ease { animation-timing-function: cubic-bezier(.65, 0, .35, 1); }

        /* trolley slides along the jib */
        .trolley {
            animation: slide var(--cycle) infinite;
            animation-timing-function: cubic-bezier(.65, 0, .35, 1);
        }
        /* cable shortens / lengthens */
        .cable {
            transform-box: fill-box;
            transform-origin: 50% 0;
            animation: cable var(--cycle) infinite;
            animation-timing-function: cubic-bezier(.65, 0, .35, 1);
        }
        /* hook + load ride the end of the cable */
        .hook {
            animation: lift var(--cycle) infinite;
            animation-timing-function: cubic-bezier(.65, 0, .35, 1);
        }
        .sway {
            transform-origin: 0 0;
            animation: sway 3.2s ease-in-out infinite;
        }
        .cargo { animation: cargo var(--cycle) infinite; }
        .placed { animation: placed var(--cycle) infinite; }
        .cloud { animation: drift 22s linear infinite; }
        .cloud.two { animation-duration: 30s; animation-delay: -12s; }
        .beacon { animation: blink 1.6s ease-in-out infinite; }

        @keyframes slide {
            0%, 18%  { transform: translateX(0); }
            42%, 76% { transform: translateX(90px); }
            90%, 100%{ transform: translateX(0); }
        }
        @keyframes cable {
            0%, 6%   { transform: scaleY(1); }
            18%, 40% { transform: scaleY(.3); }
            54%, 64% { transform: scaleY(1); }
            76%, 90% { transform: scaleY(.3); }
            100%     { transform: scaleY(1); }
        }
        @keyframes lift {
            0%, 6%   { transform: translateY(158px); }
            18%, 40% { transform: translateY(47px); }
            54%, 64% { transform: translateY(158px); }
            76%, 90% { transform: translateY(47px); }
            100%     { transform: translateY(158px); }
        }
        @keyframes sway {
            0%, 100% { transform: rotate(-1.6deg); }
            50%      { transform: rotate(1.6deg); }
        }
        /* load is carried until it is set down, then reappears at pickup */
        @keyframes cargo {
            0%, 56%  { opacity: 1; }
            60%, 94% { opacity: 0; }
            98%, 100%{ opacity: 1; }
        }
        @keyframes placed {
            0%, 56%  { opacity: 0; }
            62%, 92% { opacity: 1; }
            98%, 100%{ opacity: 0; }
        }
        @keyframes drift {
            from { transform: translateX(-60px); }
            to   { transform: translateX(460px); }
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%      { opacity: .25; }
        }

        @media (prefers-reduced-motion: reduce) {
            .trolley, .cable, .hook, .sway, .cargo, .placed, .cloud, .beacon { animation: none; }
            .cable { transform: scaleY(.6); }
            .hook  { transform: translateY(95px); }
            .placed { opacity: 0; }
        }
    </style>
</head>
<body class="font-sans antialiased min-h-dvh flex items-center justify-center p-4 sm:p-6">
    <div class="w-full max-w-3xl">

        <div class="flex items-center justify-center gap-3 pt-2 sm:pt-4">
            <img src="/logo.png" alt="One Window Bayanihan Logo" class="w-9 h-9 object-contain" />
            <div class="flex flex-col text-left" style="font-family: 'Outfit', sans-serif;">
                <span class="font-bold text-slate-800 text-sm tracking-tight leading-tight">One Window Bayanihan</span>
                <span class="text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-500">Assistance Program</span>
            </div>
        </div>

        <div class="px-6 pt-6 sm:pt-8 text-center">
            <h1 class="font-serif text-3xl sm:text-4xl font-bold text-[#3a3d42] leading-tight tracking-tight">
                The site is currently<br class="hidden sm:block"> under maintenance.
            </h1>

            <p class="mt-5 text-[13px] text-slate-600 leading-relaxed max-w-sm mx-auto">
                We apologize for any inconvenience caused.
                We're almost done&mdash;we expect to be back
                @if(isset($retry) && $retry)
                    in about <strong class="text-slate-800">{{ $retry }}</strong> minute{{ $retry > 1 ? 's' : '' }}.
                @else
                    in a few minutes.
                @endif
            </p>
        </div>

        <div class="mt-4 px-6" aria-hidden="true">
            <svg class="crane-svg w-full max-w-sm mx-auto block" viewBox="0 0 400 270" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <!-- X-braced lattice, like the reference crane -->
                    <pattern id="lattice-h" width="14" height="12" patternUnits="userSpaceOnUse">
                        <path d="M0 0 L14 12 M14 0 L0 12" stroke="#e2962a" stroke-width="1.2" fill="none"/>
                    </pattern>
                    <pattern id="lattice-v" width="16" height="16" patternUnits="userSpaceOnUse">
                        <path d="M0 0 L16 16 M16 0 L0 16" stroke="#e2962a" stroke-width="1.2" fill="none"/>
                        <path d="M0 15.5 H16" stroke="#e2962a" stroke-width="1.6"/>
                    </pattern>
                    <clipPath id="clip-world"><rect x="0" y="0" width="400" height="270"/></clipPath>
                </defs>

                <!-- clouds -->
                <g clip-path="url(#clip-world)">
                    <g class="cloud" opacity=".9">
                        <ellipse cx="40" cy="40" rx="22" ry="8" fill="#e3f5ff"/>
                        <ellipse cx="56" cy="34" rx="14" ry="8" fill="#e3f5ff"/>
                    </g>
                    <g class="cloud two" opacity=".9">
                        <ellipse cx="20" cy="95" rx="18" ry="6" fill="#e3f5ff"/>
                        <ellipse cx="32" cy="90" rx="11" ry="6" fill="#e3f5ff"/>
                    </g>
                </g>

                <!-- ground -->
                <ellipse cx="200" cy="262" rx="190" ry="10" fill="#b9f3cd"/>
                <rect x="30" y="256" width="340" height="6" rx="3" fill="#8eeaae"/>

                <!-- drop pad -->
                <rect x="198" y="253" width="50" height="4" rx="2" fill="#aee0fc"/>
                <!-- pickup pad -->
                <rect x="108" y="253" width="50" height="4" rx="2" fill="#aee0fc"/>

                <!-- beams resting on the drop pad once set down -->
                <g class="placed">
                    <rect x="206" y="247" width="34" height="6" rx="1.5" fill="#00a3f0"/>
                    <rect x="204" y="241" width="34" height="6" rx="1.5" fill="#22e05c"/>
                    <rect x="208" y="235" width="34" height="6" rx="1.5" fill="#aee0fc"/>
                </g>

                <!-- tower -->
                <rect x="262" y="250" width="56" height="8" rx="2" fill="#4a4e55"/>
                <rect x="272" y="244" width="36" height="7" rx="1.5" fill="#62666d"/>
                <rect x="282" y="52" width="16" height="193" fill="#f5b83d"/>
                <rect x="285" y="52" width="10" height="193" fill="url(#lattice-v)"/>
                <rect x="282" y="52" width="3" height="193" fill="#f5b83d"/>
                <rect x="295" y="52" width="3" height="193" fill="#f5b83d"/>

                <!-- apex mast -->
                <polygon points="290,12 284,54 296,54" fill="none" stroke="#e2962a" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M286 40 L294 28 M294 40 L286 28 M287 52 L293 44 M293 52 L287 44" stroke="#e2962a" stroke-width="1.1"/>
                <circle cx="290" cy="12" r="3.2" fill="#e2962a"/>
                <circle class="beacon" cx="290" cy="12" r="1.6" fill="#ff5b5b"/>

                <!-- cab / slewing unit -->
                <rect x="274" y="50" width="32" height="8" rx="2" fill="#4a4e55"/>
                <rect x="268" y="40" width="22" height="12" rx="2" fill="#f2cf4a"/>
                <rect x="272" y="43" width="9" height="6" rx="1" fill="#aee0fc"/>

                <!-- jib (working arm) -->
                <rect x="70" y="56" width="212" height="12" rx="1.5" fill="#f5b83d"/>
                <rect x="70" y="58" width="212" height="8" fill="url(#lattice-h)"/>
                <rect x="70" y="56" width="212" height="2.4" fill="#e2962a"/>
                <rect x="70" y="65.6" width="212" height="2.4" fill="#e2962a"/>
                <circle cx="70" cy="62" r="4.5" fill="#e2962a"/>
                <circle cx="70" cy="62" r="2" fill="#f5b83d"/>

                <!-- counter-jib -->
                <rect x="298" y="56" width="62" height="10" rx="1.5" fill="#f5b83d"/>
                <rect x="298" y="58" width="62" height="6" fill="url(#lattice-h)"/>
                <rect x="298" y="56" width="62" height="2.2" fill="#e2962a"/>
                <rect x="298" y="63.8" width="62" height="2.2" fill="#e2962a"/>
                <rect x="338" y="64" width="22" height="22" rx="2" fill="#4a4e55"/>
                <rect x="342" y="68" width="14" height="4" rx="1" fill="#62666d"/>

                <!-- tie lines (pendants) -->
                <path d="M290 12 L70 59 M290 12 L196 57 M290 12 L360 57" stroke="#4a4e55" stroke-width="1.1" fill="none"/>

                <!-- trolley + cable + hook + load -->
                <g transform="translate(130 68)">
                    <g class="trolley">
                        <rect x="-8" y="-2" width="16" height="7" rx="2" fill="#4a4e55"/>
                        <rect class="cable" x="-0.8" y="5" width="1.6" height="153" fill="#4a4e55"/>
                        <g class="hook">
                            <g class="sway">
                                <rect x="-4" y="0" width="8" height="8" rx="1.5" fill="#e2962a"/>
                                <path d="M0 8 L-18 26 M0 8 L18 26" stroke="#4a4e55" stroke-width="1" fill="none"/>
                                <g class="cargo">
                                    <rect x="-20" y="26" width="40" height="6" rx="1.5" fill="#aee0fc"/>
                                    <rect x="-22" y="32" width="40" height="6" rx="1.5" fill="#22e05c"/>
                                    <rect x="-18" y="38" width="40" height="6" rx="1.5" fill="#00a3f0"/>
                                </g>
                            </g>
                        </g>
                    </g>
                </g>
            </svg>
        </div>

        <div class="mt-5 flex justify-center">
            <button onclick="window.location.reload()" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-[#00a3f0] text-white text-[13px] font-semibold hover:bg-[#0a5fb4] transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                Try Again
            </button>
        </div>

        <p class="mt-8 pb-2 text-center text-[11px] text-slate-400">&copy; {{ date('Y') }} One Window Bayanihan. All rights reserved.</p>
    </div>
</body>
</html>