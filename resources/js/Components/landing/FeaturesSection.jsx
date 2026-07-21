import { useState, useEffect } from 'react';
import { BAYANIHAN_PHOTOS, FEATURE_SCREENSHOTS } from './appData';
import useInView from '@/Hooks/useInView';

export default function FeaturesSection() {
  const [activePhotoIndex, setActivePhotoIndex] = useState(0);
  const [headingRef, headingVisible] = useInView();
  const [galleryRef, galleryVisible] = useInView();
  const [textRef, textVisible] = useInView();
  const [cardsRef, cardsVisible] = useInView();

  useEffect(() => {
    const id = setInterval(() => {
      setActivePhotoIndex((c) => (c + 1) % BAYANIHAN_PHOTOS.length);
    }, 5000);
    return () => clearInterval(id);
  }, []);

  const photo = BAYANIHAN_PHOTOS[activePhotoIndex];

  const handlePrev = () => setActivePhotoIndex((c) => (c - 1 + BAYANIHAN_PHOTOS.length) % BAYANIHAN_PHOTOS.length);
  const handleNext = () => setActivePhotoIndex((c) => (c + 1) % BAYANIHAN_PHOTOS.length);

  return (
    <section id="features" className="bg-surface-container px-8 pt-8 pb-24">
      <div className="mx-auto max-w-7xl">
        <div ref={headingRef} className={`mb-12 max-w-4xl owb-reveal ${headingVisible ? 'is-visible' : ''}`}>
          <h2 className="font-headline text-2xl font-extrabold text-primary md:text-3xl">
            What is One Window Bayanihan and Why It Matters
          </h2>
        </div>

        <div ref={galleryRef} className={`relative overflow-hidden border border-outline-variant/30 bg-surface-container-lowest shadow-md owb-reveal-scale ${galleryVisible ? 'is-visible' : ''}`}>
          <figure className="relative">
            <img src={photo.src} alt={photo.alt} className="h-[340px] w-full object-cover md:h-[520px]" loading="lazy" referrerPolicy="no-referrer" />
            <button type="button" onClick={handlePrev} className="absolute left-4 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-circle bg-black/55 text-white transition-colors hover:bg-black/70" aria-label="Previous photo">
              <span className="material-symbols-outlined" aria-hidden="true">chevron_left</span>
            </button>
            <button type="button" onClick={handleNext} className="absolute right-4 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-circle bg-black/55 text-white transition-colors hover:bg-black/70" aria-label="Next photo">
              <span className="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            </button>
            <figcaption className="absolute bottom-0 left-0 right-0 bg-black/60 px-5 py-4 text-sm text-white backdrop-blur-sm">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <span>Source: <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" className="font-semibold underline underline-offset-2">{photo.sourceLabel}</a></span>
                <span className="text-xs uppercase tracking-[0.1em] text-white/85">{activePhotoIndex + 1} / {BAYANIHAN_PHOTOS.length}</span>
              </div>
            </figcaption>
          </figure>
          <div className="flex items-center justify-center gap-2 border-t border-outline-variant/30 bg-white px-4 py-4">
            {BAYANIHAN_PHOTOS.map((p, i) => (
              <button key={p.src} type="button" onClick={() => setActivePhotoIndex(i)}
                className={`h-2.5 rounded-full transition-all ${i === activePhotoIndex ? 'w-8 bg-primary' : 'w-2.5 bg-outline-variant/70 hover:bg-primary/60'}`}
                aria-label={`Go to photo ${i + 1}`} aria-current={i === activePhotoIndex ? 'true' : undefined}
              />
            ))}
          </div>
        </div>

        <div ref={textRef} className={`mt-12 mb-12 grid gap-10 lg:grid-cols-[1.3fr_1fr] lg:items-start owb-reveal ${textVisible ? 'is-visible' : ''}`}>
          {/* Text */}
          <article className="space-y-4 text-sm leading-relaxed text-on-surface-variant md:text-base">
            <p>One Window Bayanihan Assistance Program (OWBAP) is a unified, ICT-enabled platform designed to transform how assistance is delivered to Overseas Filipino Workers (OFWs).</p>
            <p>It serves as a centralized inter-agency referral and tracking system, enabling the Department of Migrant Workers (DMW), local government units, and partner agencies to work together through one coordinated digital platform.</p>
            <p>Built on the principle of<br />&quot;One OFW. One Entry. One Coordinated System.&quot;<br />OWBAP eliminates fragmented processes by introducing a single-entry, assisted case intake, where case managers handle encoding, service assignment, and document management, removing the need for OFWs to repeatedly submit information or navigate multiple offices.</p>
            <p>Through automated referrals, standardized workflows, and real-time case tracking via a unique tracker number, the system ensures faster response, improved transparency, and seamless coordination from assistance to reintegration.</p>
            <p>By centralizing case visibility and strengthening inter-agency collaboration, OWBAP delivers efficient, accountable, and people-centered support, ensuring that every OFW receives timely help and that no case is left behind.</p>
          </article>

          {/* Logo */}
          <div className="flex items-center justify-center drop-shadow-2xl">
            <img
              src="/logo.png"
              alt="One Window Bayanihan Assistance Program Logo"
              className="w-full max-w-sm animate-float object-contain"
              loading="lazy"
            />
          </div>
        </div>

        <div ref={cardsRef} className="owb-stagger grid grid-cols-1 gap-6 md:grid-cols-3">
          {FEATURE_SCREENSHOTS.map((f) => (
            <div key={f.title} className={`owb-reveal border border-outline-variant/30 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md ${cardsVisible ? 'is-visible' : ''}`}>
              {f.src ? (
                <img src={f.src} alt={f.alt} className="h-48 w-full object-cover md:h-56" loading="lazy" />
              ) : (
                <div className="flex h-48 w-full items-center justify-center bg-surface-container-high md:h-56">
                  <div className="text-center">
                    <span className="material-symbols-outlined block text-4xl text-outline-variant">image</span>
                    <span className="mt-2 block text-xs text-outline-variant">Screenshot placeholder</span>
                  </div>
                </div>
              )}
              <div className="p-5">
                <h3 className="mb-2 text-lg font-bold text-primary">{f.title}</h3>
                <p className="text-sm leading-relaxed text-on-surface-variant">{f.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
