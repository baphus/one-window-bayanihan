import { Link } from '@inertiajs/react';
import AppButton from './AppButton';
import useInView from '@/Hooks/useInView';

export default function AboutSection() {
  const [ref, isVisible] = useInView();

  return (
    <section id="about" className="border-y border-outline-variant/30 bg-surface-container-low px-8 py-20">
      <div ref={ref} className={`mx-auto max-w-4xl text-center owb-reveal ${isVisible ? 'is-visible' : ''}`}>
        <h2 className="mb-4 font-headline text-3xl font-extrabold text-primary">Strengthening the Network of Care</h2>
        <p className="mx-auto mb-8 max-w-2xl text-lg leading-relaxed text-on-surface-variant">
          Are you a government agency or licensed stakeholder? Join our unified platform to streamline referrals and provide faster assistance through coordinated case handling and tracker-based updates.
        </p>
        <Link href={route('login')}>
          <AppButton variant="outline" icon="handshake" className="border-2 border-primary px-6 py-3 text-base">
            Inquire Now
          </AppButton>
        </Link>
      </div>
    </section>
  );
}
