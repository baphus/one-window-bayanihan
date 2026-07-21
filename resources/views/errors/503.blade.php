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
        body {
            font-family: 'Public Sans', sans-serif;
            background: #ffffff;
        }

        #snake-wrap {
            font-family: 'Public Sans', sans-serif;
        }
        #snake-score {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #64748b;
            text-align: right;
            margin-bottom: 8px;
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
        }
        #snake-canvas {
            display: block;
            background: #ffffff;
            border: 1px solid #dbe6ee;
            border-radius: 10px;
            image-rendering: pixelated;
            width: 100%;
            max-width: 420px;
            height: auto;
            margin: 0 auto;
        }
        #snake-msg {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 10px;
            letter-spacing: 0.3px;
            height: 18px;
        }
    </style>
</head>
<body class="font-sans antialiased min-h-dvh flex items-center justify-center p-6 sm:p-10">
    <div class="w-full max-w-xl">
        <div class="px-6 py-6 sm:px-10 sm:py-8 text-center">

            <!-- logo row -->
            <div class="flex items-center justify-center gap-3 mb-6">
                <img src="/logo.png" alt="One Window Bayanihan Logo" class="w-10 h-10 object-contain" />
                <div class="flex flex-col text-left" style="font-family: 'Outfit', sans-serif;">
                    <span class="font-bold text-slate-800 text-sm tracking-tight leading-tight">One Window Bayanihan</span>
                    <span class="text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-500">Assistance Program</span>
                </div>
            </div>

            <!-- game, in place of the illustration -->
            <div id="snake-wrap">
                <div id="snake-score">HI 00000&nbsp;&nbsp;00000</div>
                <canvas id="snake-canvas" width="420" height="150"></canvas>
                <div id="snake-msg">PRESS SPACE TO START</div>
            </div>

            <h1 class="font-serif text-2xl sm:text-3xl font-bold text-slate-900 mb-3 tracking-tight mt-6">
                The website is under maintenance
            </h1>

            <p class="text-sm text-slate-500 leading-relaxed max-w-sm mx-auto mb-6">
                We sincerely apologize for the inconvenience. Our website is undergoing 
                planned updates to improve performance. We expect to be back
                @if(isset($retry) && $retry)
                    in approximately <strong class="text-slate-600">{{ $retry }}</strong> minute{{ $retry > 1 ? 's' : '' }}.
                @else
                    in a few minutes.
                @endif
            </p>

            <button onclick="window.location.reload()" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-primary text-white text-sm font-bold hover:bg-[#003d66] transition-colors shadow-md hover:shadow-lg">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                Try Again
            </button>

            <p class="mt-8 text-xs text-slate-400 italic">
                Play a round or two while you wait &mdash; refresh whenever you're ready to check again.
            </p>
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">&copy; {{ date('Y') }} One Window Bayanihan. All rights reserved.</p>
    </div>

    <script>
    (function () {
        const canvas = document.getElementById('snake-canvas');
        const ctx = canvas.getContext('2d');
        const scoreEl = document.getElementById('snake-score');
        const msgEl = document.getElementById('snake-msg');

        const GRID = 15; // cell size, smaller to fit the tighter illustration slot
        const COLS = canvas.width / GRID;
        const ROWS = canvas.height / GRID;
        const GROUND_Y = ROWS - 2;

        const COLOR = '#005288';
        const BG = '#ffffff';

        let snake, dir, nextDir, food, score, hiScore, running, gameOver, speed, timer;

        hiScore = parseInt(localStorage.getItem('owb-503-snake-hi') || '0', 10);

        function resetGame() {
            snake = [
                {x: 6, y: GROUND_Y},
                {x: 5, y: GROUND_Y},
                {x: 4, y: GROUND_Y}
            ];
            dir = {x: 1, y: 0};
            nextDir = {x: 1, y: 0};
            score = 0;
            speed = 110;
            running = false;
            gameOver = false;
            placeFood();
            updateScore();
            msgEl.textContent = 'PRESS SPACE TO START';
            draw();
        }

        function placeFood() {
            let ok = false;
            while (!ok) {
                food = {
                    x: Math.floor(Math.random() * COLS),
                    y: Math.floor(Math.random() * ROWS)
                };
                ok = !snake.some(s => s.x === food.x && s.y === food.y);
            }
        }

        function updateScore() {
            const s = String(score).padStart(5, '0');
            const h = String(hiScore).padStart(5, '0');
            scoreEl.textContent = `HI ${h}  ${s}`;
        }

        function drawGroundDashes() {
            ctx.strokeStyle = COLOR;
            ctx.lineWidth = 2;
            ctx.beginPath();
            const y = (GROUND_Y + 1) * GRID - 1;
            ctx.moveTo(0, y);
            ctx.lineTo(canvas.width, y);
            ctx.stroke();
        }

        function drawCell(cell, filled) {
            const px = cell.x * GRID;
            const py = cell.y * GRID;
            ctx.fillStyle = COLOR;
            if (filled) {
                ctx.fillRect(px + 1, py + 1, GRID - 2, GRID - 2);
            } else {
                ctx.strokeStyle = COLOR;
                ctx.lineWidth = 2;
                ctx.strokeRect(px + 3, py + 3, GRID - 6, GRID - 6);
            }
        }

        function drawTailCell(cell, scale) {
            const size = (GRID - 2) * scale;
            const px = cell.x * GRID + GRID / 2 - size / 2;
            const py = cell.y * GRID + GRID / 2 - size / 2;
            ctx.fillStyle = COLOR;
            ctx.fillRect(px, py, size, size);
        }

        function tailScale(indexFromEnd, tailCount) {
            const t = (indexFromEnd + 1) / (tailCount + 1);
            const minScale = 0.3;
            return minScale + (1 - minScale) * t;
        }

        function bridgeSegments(a, b, scaleA, scaleB) {
            const ax = a.x * GRID, ay = a.y * GRID;
            const bx = b.x * GRID, by = b.y * GRID;

            const dx = b.x - a.x, dy = b.y - a.y;
            if (Math.abs(dx) > 1 || Math.abs(dy) > 1) return;

            ctx.fillStyle = COLOR;
            if (dx !== 0) {
                const x = Math.min(ax, bx) + GRID - 1;
                const y = ay + GRID / 2 - (GRID - 2) * Math.min(scaleA, scaleB) / 2;
                ctx.fillRect(x, y, 2, (GRID - 2) * Math.min(scaleA, scaleB));
            } else if (dy !== 0) {
                const y = Math.min(ay, by) + GRID - 1;
                const x = ax + GRID / 2 - (GRID - 2) * Math.min(scaleA, scaleB) / 2;
                ctx.fillRect(x, y, (GRID - 2) * Math.min(scaleA, scaleB), 2);
            }
        }

        function drawHead(cell) {
            const px = cell.x * GRID;
            const py = cell.y * GRID;

            ctx.fillStyle = COLOR;
            ctx.fillRect(px + 1, py + 1, GRID - 2, GRID - 2);

            ctx.strokeStyle = COLOR;
            ctx.lineWidth = 2;
            ctx.strokeRect(px - 2, py - 2, GRID + 2, GRID + 2);

            const eyeSize = 2.5;
            const cx = px + GRID / 2;
            const cy = py + GRID / 2;
            const forward = GRID / 4;
            const side = 3.5;

            const perpX = dir.y !== 0 ? side : 0;
            const perpY = dir.x !== 0 ? side : 0;

            ctx.fillStyle = BG;
            ctx.fillRect(cx + dir.x * forward - perpX / 2 - eyeSize / 2, cy + dir.y * forward - perpY / 2 - eyeSize / 2, eyeSize, eyeSize);
            ctx.fillRect(cx + dir.x * forward + perpX / 2 - eyeSize / 2, cy + dir.y * forward + perpY / 2 - eyeSize / 2, eyeSize, eyeSize);
        }

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            drawCell(food, false);

            const tailCount = Math.max(2, Math.min(8, Math.round(snake.length * 0.25)));

            const scales = snake.map((seg, i) => {
                const fromEnd = snake.length - 1 - i;
                return fromEnd < tailCount ? tailScale(fromEnd, tailCount) : 1;
            });

            for (let i = 0; i < snake.length - 1; i++) {
                bridgeSegments(snake[i], snake[i + 1], scales[i], scales[i + 1]);
            }

            snake.forEach((seg, i) => {
                if (i === 0) {
                    drawHead(seg);
                } else if (scales[i] < 1) {
                    drawTailCell(seg, scales[i]);
                } else {
                    drawCell(seg, true);
                }
            });

            if (gameOver) {
                ctx.font = 'bold 15px "Public Sans", sans-serif';
                ctx.textAlign = 'center';
                ctx.lineWidth = 4;
                ctx.strokeStyle = BG;
                ctx.strokeText('GAME OVER', canvas.width / 2, canvas.height / 2 - 8);
                ctx.fillStyle = COLOR;
                ctx.fillText('GAME OVER', canvas.width / 2, canvas.height / 2 - 8);
            }
        }

        function tick() {
            if (!running) return;

            dir = nextDir;
            const head = {x: snake[0].x + dir.x, y: snake[0].y + dir.y};

            if (head.x < 0) head.x = COLS - 1;
            if (head.x >= COLS) head.x = 0;
            if (head.y < 0) head.y = ROWS - 1;
            if (head.y >= ROWS) head.y = 0;

            if (snake.some(s => s.x === head.x && s.y === head.y)) {
                endGame();
                return;
            }

            snake.unshift(head);

            if (head.x === food.x && head.y === food.y) {
                score += 10;
                if (score > hiScore) { hiScore = score; localStorage.setItem('owb-503-snake-hi', hiScore); }
                updateScore();
                placeFood();
                speed = Math.max(60, speed - 3);
                restartTimer();
            } else {
                snake.pop();
            }

            draw();
        }

        function restartTimer() {
            clearInterval(timer);
            timer = setInterval(tick, speed);
        }

        function endGame() {
            running = false;
            gameOver = true;
            clearInterval(timer);
            msgEl.textContent = 'PRESS SPACE TO RESTART';
            draw();
        }

        function startGame() {
            if (gameOver) {
                resetGame();
            }
            running = true;
            gameOver = false;
            msgEl.textContent = '';
            restartTimer();
        }

        document.addEventListener('keydown', (e) => {
            const key = e.key;

            if (key === ' ') {
                e.preventDefault();
                if (!running) startGame();
                return;
            }

            if (!running) return;

            if ((key === 'ArrowUp' || key === 'w') && dir.y === 0) nextDir = {x: 0, y: -1};
            else if ((key === 'ArrowDown' || key === 's') && dir.y === 0) nextDir = {x: 0, y: 1};
            else if ((key === 'ArrowLeft' || key === 'a') && dir.x === 0) nextDir = {x: -1, y: 0};
            else if ((key === 'ArrowRight' || key === 'd') && dir.x === 0) nextDir = {x: 1, y: 0};
        });

        let touchStartX = 0, touchStartY = 0;
        canvas.addEventListener('touchstart', (e) => {
            if (!running) { startGame(); return; }
            const t = e.touches[0];
            touchStartX = t.clientX;
            touchStartY = t.clientY;
        }, {passive: true});

        canvas.addEventListener('touchend', (e) => {
            const t = e.changedTouches[0];
            const dx = t.clientX - touchStartX;
            const dy = t.clientY - touchStartY;
            if (Math.abs(dx) > Math.abs(dy)) {
                if (dx > 20 && dir.x === 0) nextDir = {x: 1, y: 0};
                else if (dx < -20 && dir.x === 0) nextDir = {x: -1, y: 0};
            } else {
                if (dy > 20 && dir.y === 0) nextDir = {x: 0, y: 1};
                else if (dy < -20 && dir.y === 0) nextDir = {x: 0, y: -1};
            }
        }, {passive: true});

        resetGame();
    })();
    </script>
</body>
</html>