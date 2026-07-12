import { useMemo, useState } from 'react';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';

const SHAPES = {
  cebu: 'M209 40 C217 39 223 46 226 57 C229 69 230 83 230 97 C230 116 229 136 227 154 C225 171 221 188 214 201 C207 206 200 206 194 201 C190 188 188 173 187 156 C186 136 187 115 189 95 C191 77 194 58 199 46 C201 42 205 40 209 40 Z',
  bohol: 'M267 152 C278 145 292 145 304 152 C311 158 314 166 313 175 C312 183 307 190 299 194 C287 197 274 196 263 191 C255 185 251 177 252 168 C254 160 259 155 267 152 Z',
};

const PROVINCE_LABELS = {
  cebu: { x: 198, y: 220, align: 'middle' },
  bohol: { x: 286, y: 126, align: 'middle' },
};

const PROVINCE_NOTCHES = {
  cebu: 'M204 58 C208 56 213 57 217 60 C220 63 223 68 224 74',
  bohol: 'M271 161 C277 159 284 159 290 162 C295 165 299 170 300 175',
};

function colorFor(count, max, palette = COLORS.chartPalette) {
  if (!max) return palette[0];
  const ratio = Math.max(0, Math.min(1, count / max));
  const index = Math.min(palette.length - 1, Math.round(ratio * (palette.length - 1)));
  return palette[index];
}

export default function PhilippinesMap({ provinces = [], onProvinceClick, selectedProvince }) {
  const [tooltip, setTooltip] = useState(null);

  const max = useMemo(() => Math.max(...provinces.map((province) => Number(province.count ?? 0)), 0), [provinces]);

  const handleEnter = (event, province) => {
    const rect = event.currentTarget.ownerSVGElement?.getBoundingClientRect();
    if (!rect) return;

    setTooltip({
      name: province.name,
      count: Number(province.count ?? 0),
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    });
  };

  const handleActivate = (event, province) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      onProvinceClick?.(province.value ?? province.id);
    }
  };

  return (
    <div className="relative overflow-hidden rounded-[3px] border border-slate-200 bg-gradient-to-br from-cyan-50 via-sky-50 to-blue-100 shadow-sm dark:border-slate-700 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
      <svg viewBox="0 0 420 300" className="h-[320px] w-full overflow-visible" role="img" aria-label="Region VII province map">
        <defs>
          <linearGradient id="cv-water" x1="0" x2="1" y1="0" y2="1">
            <stop offset="0%" stopColor="#ecfeff" />
            <stop offset="55%" stopColor="#dff6ff" />
            <stop offset="100%" stopColor="#cfe8ff" />
          </linearGradient>
          <linearGradient id="cv-island" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stopColor="#f8fbff" stopOpacity="0.85" />
            <stop offset="100%" stopColor="#d5edf7" stopOpacity="0.3" />
          </linearGradient>
          <filter id="cv-shadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="4" stdDeviation="4" floodColor="#0f172a" floodOpacity="0.18" />
          </filter>
        </defs>

        <rect x="0" y="0" width="420" height="300" fill="url(#cv-water)" />

        <g opacity="0.35">
          <path d="M22 78 C72 56, 123 58, 166 84" fill="none" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" />
          <path d="M30 94 C84 72, 135 75, 188 103" fill="none" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" />
          <path d="M214 44 C251 39, 294 43, 340 60" fill="none" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" />
          <path d="M266 218 C311 210, 347 214, 398 233" fill="none" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" />
        </g>

        <g filter="url(#cv-shadow)">
          <path
            d="M112 48 C124 38 139 36 154 43 C160 46 166 51 170 58 C167 63 164 68 162 74 C157 88 154 103 153 118 C151 138 150 155 145 169 C141 180 136 188 130 194 C121 191 112 184 105 173 C99 163 95 149 94 133 C92 112 95 93 100 75 C103 63 107 54 112 48 Z"
            fill="url(#cv-island)"
            opacity="0.22"
          />
          <path
            d="M190 41 C206 35 223 39 234 51 C239 57 242 65 244 74 C245 84 245 96 244 109 C243 122 242 135 240 149 C238 164 235 178 230 191 C223 205 213 212 203 213 C196 208 191 200 188 188 C184 174 182 158 182 141 C181 123 182 103 184 84 C186 68 188 52 190 41 Z"
            fill="url(#cv-island)"
            opacity="0.18"
          />

          {provinces.map((province) => {
            const path = SHAPES[province.id];
            if (!path) return null;
            const isSelected = selectedProvince && [province.value, province.id, province.name].includes(selectedProvince);
            const fill = province.color || colorFor(Number(province.count ?? 0), max);
            const label = PROVINCE_LABELS[province.id];

            return (
              <g key={province.id}>
                {PROVINCE_NOTCHES[province.id] ? (
                  <path d={PROVINCE_NOTCHES[province.id]} fill="none" stroke="#ffffff" strokeOpacity="0.55" strokeWidth="2" strokeLinecap="round" />
                ) : null}
                <path
                  d={path}
                  fill={fill}
                  opacity={province.count > 0 ? 0.94 : 0.42}
                  stroke={isSelected ? '#0b5a8c' : 'rgba(255,255,255,0.95)'}
                  strokeWidth={isSelected ? 3 : 2.25}
                  strokeLinejoin="round"
                  strokeLinecap="round"
                  className="cursor-pointer transition duration-150 hover:brightness-110 focus:outline-none"
                  tabIndex={0}
                  role="button"
                  aria-label={`${province.name} ${province.count} cases`}
                  onClick={() => onProvinceClick?.(province.value ?? province.id)}
                  onKeyDown={(event) => handleActivate(event, province)}
                  onMouseEnter={(event) => handleEnter(event, province)}
                  onMouseMove={(event) => handleEnter(event, province)}
                  onMouseLeave={() => setTooltip(null)}
                >
                  <title>{`${province.name} — ${province.count} cases`}</title>
                </path>
                {label ? (
                  <g pointerEvents="none">
                    <path
                      d={`M${label.x - (label.align === 'start' ? 4 : 0)} ${label.y + 4} C${label.x - 2} ${label.y + 8}, ${label.x + 2} ${label.y + 10}, ${label.x + 6} ${label.y + 14}`}
                      fill="none"
                      stroke="#0f172a"
                      strokeOpacity="0.2"
                      strokeWidth="1"
                    />
                    <text
                      x={label.x}
                      y={label.y}
                      textAnchor={label.align}
                      className="fill-slate-700 dark:fill-slate-200"
                      fontSize="10"
                      fontWeight="800"
                      letterSpacing="0.12em"
                    >
                      {province.name.toUpperCase()}
                    </text>
                  </g>
                ) : null}
              </g>
            );
          })}
        </g>

        <g>
          <text x="24" y="28" className="fill-slate-700 dark:fill-slate-200" fontSize="11" fontWeight="900" letterSpacing="0.16em">REGION VII</text>
          <text x="24" y="44" className="fill-slate-500 dark:fill-slate-400" fontSize="9" fontWeight="700" letterSpacing="0.12em">CENTRAL VISAYAS CASE MAP</text>
          <circle cx="392" cy="34" r="12" fill="rgba(255,255,255,0.7)" stroke="#cbd5e1" strokeWidth="1" />
          <path d="M392 25 L394 32 L401 34 L394 36 L392 43 L390 36 L383 34 L390 32 Z" fill="#0b5a8c" />
        </g>
      </svg>

      {tooltip ? (
        <div
          className="pointer-events-none absolute z-10 rounded-[3px] border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 shadow-lg dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          style={{ left: Math.min(tooltip.x + 12, 300), top: Math.max(tooltip.y - 8, 8) }}
        >
          {tooltip.name} — {tooltip.count} cases
        </div>
      ) : null}
    </div>
  );
}
