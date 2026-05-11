export default function LogoMarquee({ agencies }) {
  const logos = [...new Set(agencies.map((a) => a.logo_url))];
  const marqueeLogos = [...logos, ...logos, ...logos, ...logos];

  return (
    <div className="bg-surface py-2 overflow-hidden pointer-events-none select-none">
      <div className="relative flex overflow-x-hidden">
        <div className="animate-marquee whitespace-nowrap flex items-center">
          {marqueeLogos.map((logo, i) => (
            <div key={i} className="mx-12 flex-shrink-0">
              <img src={logo} alt="Partner Agency Logo" className="h-16 w-auto max-w-[120px] object-contain opacity-80" />
            </div>
          ))}
        </div>
        <div className="absolute top-0 animate-marquee2 whitespace-nowrap flex items-center">
          {marqueeLogos.map((logo, i) => (
            <div key={`dup-${i}`} className="mx-12 flex-shrink-0">
              <img src={logo} alt="Partner Agency Logo" className="h-16 w-auto max-w-[120px] object-contain opacity-80" />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
