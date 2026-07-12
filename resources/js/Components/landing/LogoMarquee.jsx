import { useState } from 'react';

const FALLBACK_LOGO = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="120" height="80" viewBox="0 0 120 80"%3E%3Crect width="120" height="80" fill="%23f1f5f9" rx="8"/%3E%3Ctext x="60" y="44" text-anchor="middle" fill="%2394a3b8" font-size="10" font-family="system-ui"%3ELogo%3C/text%3E%3C/svg%3E';

function LogoImage({ src, alt }) {
  const [imgSrc, setImgSrc] = useState(src);
  const [failed, setFailed] = useState(false);

  if (failed) {
    return (
      <div className="flex h-20 w-32 items-center justify-center rounded bg-slate-100/20">
        <span className="text-[10px] font-semibold uppercase tracking-wider text-white/40">Logo</span>
      </div>
    );
  }

  return (
    <img
      src={imgSrc}
      alt={alt}
      className="h-20 w-auto max-w-[160px] object-contain opacity-80 transition-opacity duration-300 hover:opacity-100"
      referrerPolicy="no-referrer"
      loading="lazy"
      onError={() => {
        if (imgSrc !== FALLBACK_LOGO) {
          setImgSrc(FALLBACK_LOGO);
        } else {
          setFailed(true);
        }
      }}
    />
  );
}

export default function LogoMarquee({ agencies }) {
  const logos = [...new Set(agencies.map((a) => a.logo_url).filter(Boolean))];
  const marqueeLogos = [...logos, ...logos];

  if (logos.length === 0) return null;

  return (
    <div className="overflow-hidden select-none group">
      <div className="animate-marquee whitespace-nowrap flex items-center">
        {marqueeLogos.map((logo, i) => (
          <div key={i} className="mx-16 flex-shrink-0">
            <LogoImage src={logo} alt="Partner Agency Logo" />
          </div>
        ))}
      </div>
    </div>
  );
}
