{/* ⚠️ AI-DRAFTED CONTENT — Requires legal review before production deployment */}
{/* This Privacy Policy references RA 10173 (Data Privacy Act of 2012) and DMW Act RA 11641 */}
{/* Review and adapt to actual data processing practices before going live */}

import { Head, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import useInView from '@/Hooks/useInView';

function Section({ children }) {
  const [ref, isVisible] = useInView({ threshold: 0.05 });
  return (
    <div ref={ref} className={`owb-reveal ${isVisible ? 'is-visible' : ''}`}>
      {children}
    </div>
  );
}

const sections = [
  { id: 'introduction', num: '1', title: 'Introduction', icon: 'info' },
  { id: 'information-collected', num: '2', title: 'Information We Collect', icon: 'folder_open' },
  { id: 'how-we-use', num: '3', title: 'How We Use Your Information', icon: 'settings' },
  { id: 'legal-basis', num: '4', title: 'Legal Basis for Processing', icon: 'gavel' },
  { id: 'information-sharing', num: '5', title: 'Information Sharing and Disclosure', icon: 'share' },
  { id: 'data-security', num: '6', title: 'Data Security', icon: 'shield' },
  { id: 'data-retention', num: '7', title: 'Data Retention', icon: 'schedule' },
  { id: 'your-rights', num: '8', title: 'Your Rights Under the Data Privacy Act', icon: 'verified_user' },
  { id: 'changes', num: '9', title: 'Changes to This Privacy Policy', icon: 'edit_note' },
  { id: 'contact', num: '10', title: 'Contact Information', icon: 'mail' },
];

export default function PrivacyPolicy() {
  const [tocRef, tocVisible] = useInView();

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Privacy Policy" />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1 pt-20">
        {/* Hero */}
        <section className="relative flex min-h-[300px] w-full items-center justify-center overflow-hidden bg-[#0b5c92]">
          <div className="absolute inset-0 bg-gradient-to-br from-[#0b5c92] via-[#0b5c92]/90 to-blue-800/30"></div>
          <div className="relative z-10 mx-auto w-full max-w-7xl px-4 text-center md:px-8">
            <h1 className="mb-4 font-headline text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
              Privacy Policy
            </h1>
            <p className="mx-auto max-w-2xl text-lg leading-relaxed text-white/80">
              How we collect, use, and protect your personal data in compliance with Philippine law.
            </p>
          </div>
        </section>

        <section className="px-4 py-16">
          <div className="mx-auto max-w-5xl">
            {/* Table of Contents */}
            <nav ref={tocRef} className={`mb-12 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm owb-reveal ${tocVisible ? 'is-visible' : ''}`}>
              <h2 className="mb-4 flex items-center gap-2 text-sm font-extrabold uppercase tracking-[0.08em] text-primary">
                <span className="material-symbols-outlined text-lg">toc</span>
                Table of Contents
              </h2>
              <div className="grid grid-cols-1 gap-1 sm:grid-cols-2">
                {sections.map((s) => (
                  <a
                    key={s.id}
                    href={`#${s.id}`}
                    className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                  >
                    <span className="material-symbols-outlined text-base text-outline-variant">{s.icon}</span>
                    <span className="font-medium text-on-surface/70">{s.num}.</span>
                    <span>{s.title}</span>
                  </a>
                ))}
              </div>
            </nav>

            <div className="space-y-6">
              {/* 1. Introduction */}
              <Section>
                <article id="introduction" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                  <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                      <span className="material-symbols-outlined text-xl text-primary">info</span>
                    </div>
                    <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">1. Introduction</h2>
                  </div>
                  <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    <p>
                      The One Window Bayanihan Assistance Program (&ldquo;we,&rdquo; &ldquo;us,&rdquo; or &ldquo;our&rdquo;) is the
                      digital case management platform of the Department of Migrant Workers (DMW) Region VII. We are
                      committed to protecting the privacy and security of your personal data in accordance with Republic
                      Act No. 10173, otherwise known as the <strong>Data Privacy Act of 2012</strong>, and its
                      Implementing Rules and Regulations.
                    </p>
                    <p>
                      This Privacy Policy explains how we collect, use, disclose, and safeguard your personal information
                      when you use our platform to file, track, and manage your case or avail of our services as an
                      Overseas Filipino Worker (OFW) or stakeholder.
                    </p>
                  </div>
                </article>
              </Section>

              {/* 2. Information We Collect */}
              <Section>
                <article id="information-collected" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                  <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                      <span className="material-symbols-outlined text-xl text-primary">folder_open</span>
                    </div>
                    <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">2. Information We Collect</h2>
                  </div>
                  <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    <p>We collect the following categories of personal data to process and manage your concerns:</p>
                    <ul className="mt-3 space-y-3">
                      {[
                        ['badge', 'Personal Identifiers', 'Full name, date of birth, place of birth, gender, civil status, and nationality.'],
                        ['contact_mail', 'Contact Information', 'Home address, mobile number, email address, and emergency contact details.'],
                        ['credit_card', 'Government-Issued IDs', 'Passport number, Philippine Passport details, Unified Multi-Purpose ID (UMID), Philippine Identification System (PhilSys ID) number, and other valid government-issued identification documents.'],
                        ['work', 'Employment and Case Data', 'Employment contract details, employer information, POEA/DMW processing records, case history, referrals, supporting documents, and correspondence related to your case.'],
                        ['devices', 'Technical Data', 'IP address, browser type, device information, and usage analytics collected automatically when you access the platform.'],
                      ].map(([icon, title, desc]) => (
                        <li key={title} className="flex gap-3 rounded-xl border border-outline-variant/20 bg-surface-container p-4">
                          <span className="material-symbols-outlined mt-0.5 shrink-0 text-lg text-primary/70">{icon}</span>
                          <div>
                            <strong className="text-on-surface">{title}:</strong>{' '}
                            <span className="text-on-surface-variant">{desc}</span>
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                </article>
              </Section>

              {/* 3. How We Use Your Information */}
              <Section>
                <article id="how-we-use" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                  <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                      <span className="material-symbols-outlined text-xl text-primary">settings</span>
                    </div>
                    <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">3. How We Use Your Information</h2>
                  </div>
                  <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    <p>We process your personal data solely for the following legitimate purposes:</p>
                    <ul className="mt-3 space-y-3">
                      {[
                        ['folder_special', 'Case Management', 'To receive, process, track, and resolve your concerns, complaints, or requests filed through the One Window Bayanihan Assistance Program.'],
                        ['swap_horiz', 'Referrals and Coordination', 'To refer your case to the appropriate partner agency (OWWA, DOLE, DSWD, TESDA, DOH, POEA, or other government entities) when necessary for case resolution.'],
                        ['policy', 'Legal Compliance', 'To comply with legal obligations under RA 10173, RA 11641 (DMW Act), and other applicable laws, rules, and regulations.'],
                        ['bar_chart', 'Reporting and Analytics', 'To generate anonymized statistical reports for program improvement, policy development, and public accountability.'],
                        ['chat', 'Communication', 'To contact you regarding the status of your case, requests for additional information, and other case-related matters.'],
                      ].map(([icon, title, desc]) => (
                        <li key={title} className="flex gap-3 rounded-xl border border-outline-variant/20 bg-surface-container p-4">
                          <span className="material-symbols-outlined mt-0.5 shrink-0 text-lg text-primary/70">{icon}</span>
                          <div>
                            <strong className="text-on-surface">{title}:</strong>{' '}
                            <span className="text-on-surface-variant">{desc}</span>
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                </article>
              </Section>

              {/* 4. Legal Basis for Processing */}
              <Section>
                <article id="legal-basis" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                  <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                      <span className="material-symbols-outlined text-xl text-primary">gavel</span>
                    </div>
                    <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">4. Legal Basis for Processing</h2>
                  </div>
                  <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    <p>
                      Our processing of your personal data is grounded on the following legal bases under the
                      Data Privacy Act of 2012:
                    </p>
                    <ul className="mt-3 space-y-3">
                      {[
                        ['check_circle', 'Consent', 'Where you have voluntarily provided your personal data and explicitly consented to its processing for the purposes stated in this policy.'],
                        ['handshake', 'Contractual Necessity', 'Processing is necessary for the performance of a contract to which you are a party, including the filing and management of your case.'],
                        ['gavel', 'Legal Obligation', 'Processing is required by law, including our mandate under Republic Act No. 11641 (Department of Migrant Workers Act), which establishes DMW as the lead agency for the protection of OFW rights and welfare.'],
                        ['public', 'Public Interest', 'Processing is necessary for the performance of a task carried out in the public interest, specifically the delivery of migrant worker services and the administration of government programs for OFWs.'],
                      ].map(([icon, title, desc]) => (
                        <li key={title} className="flex gap-3 rounded-xl border border-outline-variant/20 bg-surface-container p-4">
                          <span className="material-symbols-outlined mt-0.5 shrink-0 text-lg text-primary/70">{icon}</span>
                          <div>
                            <strong className="text-on-surface">{title}:</strong>{' '}
                            <span className="text-on-surface-variant">{desc}</span>
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                </article>
              </Section>

              {/* 5. Information Sharing and Disclosure */}
              <Section>
                <article id="information-sharing" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                  <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                      <span className="material-symbols-outlined text-xl text-primary">share</span>
                    </div>
                    <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">5. Information Sharing and Disclosure</h2>
                  </div>
                  <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    <p>
                      Your personal data may be shared with the following government partner agencies strictly on a
                      need-to-know basis and only to the extent necessary for case resolution:
                    </p>
                    <div className="my-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                      {[
                        'Overseas Workers Welfare Administration (OWWA)',
                        'Department of Labor and Employment (DOLE)',
                        'Department of Social Welfare and Development (DSWD)',
                        'Technical Education and Skills Development Authority (TESDA)',
                        'Department of Health (DOH)',
                        'Philippine Overseas Employment Administration (POEA)',
                      ].map((agency) => (
                        <div key={agency} className="flex items-center gap-2 rounded-lg bg-surface-container px-3 py-2">
                          <span className="material-symbols-outlined text-base text-primary/60">apartment</span>
                          <span className="text-sm font-medium text-on-surface">{agency}</span>
                        </div>
                      ))}
                    </div>
                    <p>
                      We do <strong>not</strong> sell, trade, or rent your personal data to third parties for
                      commercial purposes. Disclosure to law enforcement or regulatory bodies shall only occur
                      when required by law or pursuant to a valid legal order.
                    </p>
                  </div>
                </article>
              </Section>

              {/* 6. Data Security */}
              <Section>
                <article id="data-security" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                  <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                      <span className="material-symbols-outlined text-xl text-primary">shield</span>
                    </div>
                    <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">6. Data Security</h2>
                  </div>
                  <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    <p>
                      We implement appropriate organizational, physical, and technical security measures to protect
                      your personal data against unauthorized access, alteration, disclosure, or destruction. These
                      measures include:
                    </p>
                    <ul className="mt-3 space-y-3">
                      {[
                        ['lock', 'Encryption', 'All data transmitted between your device and our servers is encrypted using industry-standard TLS protocols. Data at rest is encrypted using approved cryptographic algorithms.'],
                        ['admin_panel_settings', 'Access Controls', 'Role-based access controls ensure that only authorized personnel have access to personal data, and only to the extent required for their official functions.'],
                        ['fact_check', 'Regular Audits', 'We conduct periodic security audits, vulnerability assessments, and penetration testing to identify and address potential risks.'],
                        ['description', 'Data Processing Agreements', 'All third-party service providers who process data on our behalf are bound by contractual obligations to maintain the confidentiality and security of personal data.'],
                      ].map(([icon, title, desc]) => (
                        <li key={title} className="flex gap-3 rounded-xl border border-outline-variant/20 bg-surface-container p-4">
                          <span className="material-symbols-outlined mt-0.5 shrink-0 text-lg text-primary/70">{icon}</span>
                          <div>
                            <strong className="text-on-surface">{title}:</strong>{' '}
                            <span className="text-on-surface-variant">{desc}</span>
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                </article>
              </Section>

              {/* 7. Data Retention */}
              <Section>
                <article id="data-retention" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                  <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                      <span className="material-symbols-outlined text-xl text-primary">schedule</span>
                    </div>
                    <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">7. Data Retention</h2>
                  </div>
                  <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    <p>
                      We retain your personal data only for as long as necessary to fulfill the purposes for which
                      it was collected, or as required by applicable laws and regulations. Retention periods are
                      determined in accordance with:
                    </p>
                    <ul className="mt-3 list-disc pl-5 space-y-1">
                      <li>The Data Privacy Act of 2012 (RA 10173) and its IRR</li>
                      <li>The Department of Migrant Workers Act (RA 11641)</li>
                      <li>National Archives of the Philippines (NAP) retention schedules for government records</li>
                      <li>Other relevant issuances by the National Privacy Commission (NPC)</li>
                    </ul>
                    <p>
                      Once the retention period expires, personal data shall be anonymized, archived, or securely
                      disposed of in a manner that prevents further processing or unauthorized access.
                    </p>
                  </div>
                </article>
              </Section>

              {/* 8. Your Rights */}
              <Section>
                <article id="your-rights" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                  <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                      <span className="material-symbols-outlined text-xl text-primary">verified_user</span>
                    </div>
                    <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">8. Your Rights Under the Data Privacy Act</h2>
                  </div>
                  <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    <p>
                      As a data subject, you have the following rights under RA 10173, which we respect and facilitate:
                    </p>
                    <ul className="mt-3 space-y-3">
                      {[
                        ['info', 'Right to be Informed', 'To be informed of the collection and processing of your personal data, including the purposes and legal bases thereof.'],
                        ['visibility', 'Right to Access', 'To access and obtain a copy of your personal data held by our system.'],
                        ['edit', 'Right to Rectification', 'To request the correction of any inaccurate or incomplete personal data.'],
                        ['delete', 'Right to Erasure or Blocking', 'To request the suspension, withdrawal, or removal of your personal data from our files, subject to legal retention requirements.'],
                        ['block', 'Right to Object', 'To object to the processing of your personal data, including processing for direct marketing or automated decision-making.'],
                        ['download', 'Right to Data Portability', 'To receive your personal data in a commonly used, machine-readable format and to transmit such data to another processing system.'],
                        ['payments', 'Right to Damages', 'To claim compensation for damages sustained due to violation of your data privacy rights.'],
                      ].map(([icon, title, desc]) => (
                        <li key={title} className="flex gap-3 rounded-xl border border-outline-variant/20 bg-surface-container p-4">
                          <span className="material-symbols-outlined mt-0.5 shrink-0 text-lg text-primary/70">{icon}</span>
                          <div>
                            <strong className="text-on-surface">{title}:</strong>{' '}
                            <span className="text-on-surface-variant">{desc}</span>
                          </div>
                        </li>
                      ))}
                    </ul>
                    <p className="mt-4">
                      To exercise any of these rights, please contact our Data Protection Officer using the
                      contact details provided below.
                    </p>
                  </div>
                </article>
              </Section>

              {/* 9. Changes to This Privacy Policy */}
              <Section>
                <article id="changes" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                  <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                      <span className="material-symbols-outlined text-xl text-primary">edit_note</span>
                    </div>
                    <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">9. Changes to This Privacy Policy</h2>
                  </div>
                  <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    <p>
                      We reserve the right to update or modify this Privacy Policy at any time. Changes will
                      take effect immediately upon posting on this page. We encourage you to review this
                      Privacy Policy periodically to stay informed about how we are protecting your data.
                      Material changes will be communicated through our platform or via the contact
                      information you have provided.
                    </p>
                  </div>
                </article>
              </Section>

              {/* 10. Contact Information */}
              <Section>
                <article id="contact" className="scroll-mt-24 overflow-hidden rounded-lg border border-[#015289]/20 shadow-sm">
                  <div className="bg-[#015289] px-6 py-4 md:px-8">
                    <div className="flex items-center gap-3">
                      <span className="material-symbols-outlined text-2xl text-white">mail</span>
                      <h2 className="font-headline text-xl font-extrabold text-white md:text-2xl">10. Contact Information</h2>
                    </div>
                  </div>
                  <div className="bg-surface-container-lowest p-6 md:p-8">
                    <p className="mb-5 text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                      For questions, concerns, or requests regarding this Privacy Policy or the processing of
                      your personal data, you may reach us through the following:
                    </p>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      {[
                        ['person', 'Data Protection Officer', 'DMW Region VII — Data Privacy Office'],
                        ['location_on', 'Office Address', 'DG/F, DOLE Bldg. (Old Insular Bldg.), Gorordo, cor General Maxilom Ave, Cebu City'],
                        ['email', 'Email', 'dpo@bayanihan.gov.ph'],
                        ['call', 'Hotline', '(032) 268-8566 | 1348 (OFW Hotline)'],
                      ].map(([icon, label, value]) => (
                        <div key={label} className="flex gap-3 rounded-xl bg-surface-container p-4">
                          <span className="material-symbols-outlined mt-0.5 shrink-0 text-lg text-primary">{icon}</span>
                          <div>
                            <p className="text-xs font-bold uppercase tracking-wide text-on-surface/50">{label}</p>
                            <p className="mt-0.5 text-sm font-medium text-on-surface">{value}</p>
                          </div>
                        </div>
                      ))}
                    </div>
                    <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                      <p className="flex items-start gap-2 text-sm text-amber-800">
                        <span className="material-symbols-outlined mt-0.5 shrink-0 text-lg">info</span>
                        <span>
                          You may also file a complaint with the National Privacy Commission at{' '}
                          <strong>npc.gov.ph</strong> or at (02) 8234-2222, should you find that your data
                          privacy rights have been violated.
                        </span>
                      </p>
                    </div>
                  </div>
                </article>
              </Section>

              {/* Footer note */}
              <div className="mt-8 rounded-lg border border-outline-variant/20 bg-surface-container p-6 text-center">
                <p className="text-xs leading-relaxed text-on-surface/50">
                  This Privacy Policy is drafted in compliance with Republic Act No. 10173 (Data Privacy Act
                  of 2012) and its Implementing Rules and Regulations. For the full text of the law, visit
                  the official website of the National Privacy Commission at www.privacy.gov.ph.
                </p>
              </div>
            </div>
          </div>
        </section>
      </main>

      <AppFooter />
    </div>
  );
}
