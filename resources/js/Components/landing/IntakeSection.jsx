import { router } from '@inertiajs/react';
import AppButton from './AppButton';
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
          <AppButton
            variant="primary"
            icon="edit_note"
            className="px-8 py-3 text-base"
            onClick={() => router.get(route('intake.index'))}
          >
            File a Case
          </AppButton>
        </div>
      </div>
    </section>
  );
}
