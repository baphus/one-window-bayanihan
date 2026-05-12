export default function LogoMarquee({ agencies }) {
  const logos = [...new Set(agencies.map((a) => a.logo_url))];
  const marqueeLogos = [...logos, ...logos, ...logos, ...logos];

  return (
    <div className="overflow-hidden select-none group">
      <div className="relative flex overflow-x-hidden">
        <div className="animate-marquee whitespace-nowrap flex items-center">
          {marqueeLogos.map((logo, i) => (
            <div key={i} className="mx-16 flex-shrink-0">
              <img src={logo} alt="Partner Agency Logo" className="h-20 w-auto max-w-[160px] object-contain opacity-80" />
            </div>
          ))}
        </div>
        <div className="absolute top-0 animate-marquee2 whitespace-nowrap flex items-center">
          {marqueeLogos.map((logo, i) => (
            <div key={`dup-${i}`} className="mx-16 flex-shrink-0">
              <img src={logo} alt="Partner Agency Logo" className="h-20 w-auto max-w-[160px] object-contain opacity-80" />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
