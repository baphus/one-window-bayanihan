import { router } from '@inertiajs/react';
import useInView from '@/Hooks/useInView';

export default function IntakeSection() {
  const [iconRef, iconVisible] = useInView();
  const [headingRef, headingVisible] = useInView();
  const [textRef, textVisible] = useInView();
  const [ctaRef, ctaVisible] = useInView();

  return (
    <section id="intake" className="bg-gradient-to-b from-surface to-slate-50 px-8 py-20">
      <div className="mx-auto max-w-4xl text-center">
        <span ref={iconRef} className={`material-symbols-outlined mb-4 block text-4xl text-primary/60 owb-reveal ${iconVisible ? 'is-visible' : ''}`}>
          support_agent
        </span>
        <h2 ref={headingRef} className={`mb-4 font-headline text-2xl font-extrabold text-slate-900 md:text-3xl owb-reveal ${headingVisible ? 'is-visible' : ''}`}>
          Need Assistance?
        </h2>
        <p ref={textRef} className={`mx-auto mb-8 max-w-xl text-sm leading-relaxed text-slate-600 md:text-base owb-reveal ${textVisible ? 'is-visible' : ''}`}>
          If you are a distressed Overseas Filipino Worker, you can file a case request online. Our Case Managers will review your submission and coordinate with partner agencies to help you.
        </p>
        <div ref={ctaRef} className={`owb-reveal ${ctaVisible ? 'is-visible' : ''}`}>
          <button
            type="button"
            onClick={() => router.get(route('intake.index'))}
            className="inline-flex items-center justify-center gap-2 rounded-none bg-primary px-6 py-2.5 text-[14px] font-bold text-white transition-all hover:brightness-110 active:scale-95 px-8 py-3 text-base"
          >
            <span className="material-symbols-outlined">edit_note</span>
            File a Case
          </button>
        </div>
      </div>
    </section>
  );
}
