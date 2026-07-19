{/* ⚠️ AI-DRAFTED CONTENT — Requires legal review before production deployment */}
import { Head, Link, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';

const sections = [
  { id: 'acceptance', title: 'Acceptance of Terms', icon: 'handshake' },
  { id: 'description', title: 'Description of Service', icon: 'description' },
  { id: 'obligations', title: 'User Obligations', icon: 'task_alt' },
  { id: 'registration', title: 'Account Registration', icon: 'person_add' },
  { id: 'conduct', title: 'User Conduct', icon: 'gpp_bad' },
  { id: 'ip', title: 'Intellectual Property', icon: 'copyright' },
  { id: 'liability', title: 'Limitation of Liability', icon: 'balance' },
  { id: 'privacy', title: 'Privacy', icon: 'lock' },
  { id: 'governing-law', title: 'Governing Law', icon: 'account_balance' },
  { id: 'changes', title: 'Changes to Terms', icon: 'edit_note' },
  { id: 'contact', title: 'Contact Information', icon: 'mail' },
];

export default function TermsOfService() {
  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Terms of Service" />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1">
        {/* Hero */}
        <section className="relative flex min-h-[320px] w-full items-center justify-center overflow-hidden bg-[#015289]">
          <div className="absolute inset-0 bg-[#015289]" />
          <div className="relative z-10 mx-auto w-full max-w-7xl px-4 text-center md:px-8">
            <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center">
              <span className="material-symbols-outlined text-4xl text-white/60">contract</span>
            </div>
            <h1 className="mb-4 font-headline text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
              Terms of Service
            </h1>
            <p className="mx-auto max-w-2xl text-lg leading-relaxed text-white/80">
              Please read these terms carefully before using the One Window Bayanihan system.
            </p>
            <div className="mt-6 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm text-white/90 backdrop-blur-sm">
              <span className="material-symbols-outlined text-base">update</span>
              Last Updated: June 1, 2026
            </div>
          </div>
        </section>

        <section className="px-4 py-16">
          <div className="mx-auto max-w-5xl">
            {/* Table of Contents */}
            <nav className="mb-12 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm">
              <h2 className="mb-4 flex items-center gap-2 text-sm font-extrabold uppercase tracking-[0.08em] text-primary">
                <span className="material-symbols-outlined text-lg">toc</span>
                Table of Contents
              </h2>
              <div className="grid grid-cols-1 gap-1 sm:grid-cols-2">
                {sections.map((s, i) => (
                  <a
                    key={s.id}
                    href={`#${s.id}`}
                    className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                  >
                    <span className="material-symbols-outlined text-base text-outline-variant">{s.icon}</span>
                    <span className="font-medium text-on-surface/70">{i + 1}.</span>
                    <span>{s.title}</span>
                  </a>
                ))}
              </div>
            </nav>

            <div className="space-y-6">
              {/* 1. Acceptance of Terms */}
              <article id="acceptance" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <span className="material-symbols-outlined text-xl text-primary">handshake</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">1. Acceptance of Terms</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>
                    By accessing or using the One Window Bayanihan system (the &ldquo;System&rdquo;), operated by the
                    Department of Migrant Workers (DMW) Region VII, you agree to be bound by these Terms
                    of Service (the &ldquo;Terms&rdquo;). If you do not agree to all of these Terms, you are not
                    authorized to access or use the System. These Terms constitute a legally binding
                    agreement between you and the DMW Region VII governing your use of the System and
                    any services provided therein.
                  </p>
                </div>
              </article>

              {/* 2. Description of Service */}
              <article id="description" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <span className="material-symbols-outlined text-xl text-primary">description</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">2. Description of Service</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>
                    The One Window Bayanihan system is a centralized digital case management platform
                    designed to streamline the processing, tracking, and referral of concerns involving
                    Overseas Filipino Workers (OFWs) and their families. The System facilitates
                    coordination among DMW, OWWA, DOLE, DOH, DSWD, TESDA, and other partner government
                    agencies to ensure timely and efficient delivery of assistance and services. The
                    System provides case registration, document management, referral tracking, and
                    communication tools for authorized personnel and stakeholders.
                  </p>
                </div>
              </article>

              {/* 3. User Obligations */}
              <article id="obligations" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <span className="material-symbols-outlined text-xl text-primary">task_alt</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">3. User Obligations</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>As a user of the System, you agree to:</p>
                  <ul className="mt-3 space-y-3">
                    {[
                      ['fact_check', 'Provide Accurate Information', 'You must provide true, accurate, current, and complete information when registering for an account or submitting case-related data. Knowingly submitting false or misleading information may result in account suspension and referral to appropriate legal authorities.'],
                      ['balance', 'Use Lawfully', 'You shall use the System only for lawful purposes and in accordance with these Terms. You may not use the System for any fraudulent, abusive, or otherwise illegal activity.'],
                      ['lock', 'Maintain Confidentiality', 'You are responsible for maintaining the confidentiality of your account credentials, including your username and password. You must notify DMW Region VII immediately of any unauthorized use of your account or any other breach of security.'],
                      ['policy', 'Comply with Applicable Laws', 'You shall comply with all applicable Philippine laws and regulations, including but not limited to the Data Privacy Act of 2012 (Republic Act No. 10173) and the Department of Migrant Workers Act (Republic Act No. 11641).'],
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

              {/* 4. Account Registration */}
              <article id="registration" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <span className="material-symbols-outlined text-xl text-primary">person_add</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">4. Account Registration</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>
                    Access to certain features of the System requires account registration. The following
                    categories of users may register for accounts:
                  </p>
                  <ul className="mt-3 space-y-3">
                    {[
                      ['badge', 'Case Managers', 'DMW personnel authorized to create, process, and manage case records within the System.'],
                      ['apartment', 'Agency Staff', 'Personnel from partner government agencies authorized to receive referrals, update case statuses, and coordinate with DMW on shared cases.'],
                      ['admin_panel_settings', 'Administrators', 'Authorized system administrators with elevated privileges to manage user accounts, system settings, and audit trails.'],
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
                  <p className="mt-3">
                    All account registrations are subject to approval by DMW Region VII. The DMW reserves
                    the right to deny, suspend, or terminate any account at its sole discretion and
                    without prior notice if it determines that the user has violated these Terms or poses
                    a security risk to the System.
                  </p>
                </div>
              </article>

              {/* 5. User Conduct */}
              <article id="conduct" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50">
                    <span className="material-symbols-outlined text-xl text-red-500">gpp_bad</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">5. User Conduct</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>The following activities are strictly prohibited while using the System:</p>
                  <ul className="mt-3 space-y-3">
                    {[
                      ['no_accounts', 'Unauthorized Access', 'Attempting to access, probe, or connect to computing systems or accounts without authorization, including attempting to bypass authentication mechanisms or access data not intended for you.'],
                      ['download_off', 'Data Misuse', 'Accessing, downloading, modifying, copying, distributing, or disclosing any data or information from the System without proper authorization or for purposes beyond the scope of your official duties.'],
                      ['fraud', 'Fraudulent Activity', 'Submitting false or fabricated case information, documents, or evidence. Impersonating another person or entity, or misrepresenting your affiliation with any person or entity.'],
                      ['bug_report', 'Interference', 'Uploading or transmitting viruses, malware, or any other malicious code that may disrupt, damage, or impair the functionality of the System or any connected network.'],
                      ['storefront', 'Commercial Exploitation', 'Using the System for commercial solicitation, advertising, or any activity that generates profit without the express written consent of DMW Region VII.'],
                    ].map(([icon, title, desc]) => (
                      <li key={title} className="flex gap-3 rounded-xl border border-outline-variant/20 bg-surface-container p-4">
                        <span className="material-symbols-outlined mt-0.5 shrink-0 text-lg text-red-400">{icon}</span>
                        <div>
                          <strong className="text-on-surface">{title}:</strong>{' '}
                          <span className="text-on-surface-variant">{desc}</span>
                        </div>
                      </li>
                    ))}
                  </ul>
                </div>
              </article>

              {/* 6. Intellectual Property */}
              <article id="ip" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <span className="material-symbols-outlined text-xl text-primary">copyright</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">6. Intellectual Property</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>
                    The System, including its design, code, databases, interfaces, logos, and all
                    related intellectual property, is owned by the Department of Migrant Workers or its
                    licensors. No part of the System may be reproduced, distributed, modified, or
                    otherwise used without prior written authorization. Users retain ownership of any
                    content, documents, or data they lawfully submit to the System, but grant DMW a
                    non-exclusive, royalty-free license to use, store, and process such content for the
                    purposes of operating the System and delivering services to the public.
                  </p>
                </div>
              </article>

              {/* 7. Limitation of Liability */}
              <article id="liability" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <span className="material-symbols-outlined text-xl text-primary">balance</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">7. Limitation of Liability</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>
                    The One Window Bayanihan system is provided on an &ldquo;as is&rdquo; and
                    &ldquo;as available&rdquo; basis. To the fullest extent permitted by applicable
                    law, DMW Region VII disclaims all warranties, express or implied, including but not
                    limited to warranties of merchantability, fitness for a particular purpose, and
                    non-infringement. The DMW shall not be liable for any direct, indirect, incidental,
                    special, consequential, or exemplary damages arising from or in connection with your
                    use of the System, including but not limited to loss of data, service interruption,
                    or unauthorized access to information. This government system is maintained with
                    industry-standard security measures; however, no system is entirely immune to
                    security breaches. Users are encouraged to exercise appropriate caution and to
                    report any security concerns immediately.
                  </p>
                </div>
              </article>

              {/* 8. Privacy */}
              <article id="privacy" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <span className="material-symbols-outlined text-xl text-primary">lock</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">8. Privacy</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>
                    Your use of the System is subject to our{' '}
                    <Link href={route('privacy')} className="font-semibold text-primary hover:underline">
                      Privacy Policy
                    </Link>
                    , which describes how we collect, use, store, and protect your personal information.
                    By using the System, you consent to the data practices described in the Privacy
                    Policy. Please review it carefully to understand our commitment to protecting your
                    privacy and complying with the Data Privacy Act of 2012 (Republic Act No. 10173).
                  </p>
                </div>
              </article>

              {/* 9. Governing Law */}
              <article id="governing-law" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <span className="material-symbols-outlined text-xl text-primary">account_balance</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">9. Governing Law</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>
                    These Terms shall be governed by and construed in accordance with the laws of the
                    Republic of the Philippines. Any disputes arising out of or relating to these Terms
                    or your use of the System shall be resolved exclusively in the appropriate courts
                    of Cebu City, Philippines. This provision applies notwithstanding any conflict of
                    law principles. The following laws are particularly relevant to these Terms:
                  </p>
                  <ul className="mt-3 list-disc pl-5 space-y-1">
                    <li>
                      Republic Act No. 10173 — Data Privacy Act of 2012, governing the protection of
                      personal information.
                    </li>
                    <li>
                      Republic Act No. 11641 — Department of Migrant Workers Act, establishing the
                      DMW and defining its mandate and functions.
                    </li>
                    <li>
                      Republic Act No. 10844 — Department of Information and Communications Technology
                      Act, establishing standards for government digital systems.
                    </li>
                  </ul>
                </div>
              </article>

              {/* 10. Changes to Terms */}
              <article id="changes" className="scroll-mt-24 rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8">
                <div className="mb-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <span className="material-symbols-outlined text-xl text-primary">edit_note</span>
                  </div>
                  <h2 className="font-headline text-xl font-extrabold text-primary md:text-2xl">10. Changes to Terms</h2>
                </div>
                <div className="prose prose-slate max-w-none text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                  <p>
                    DMW Region VII reserves the right to modify, amend, or update these Terms at any
                    time and without prior individual notice. Material changes will be communicated
                    through the System, including by updating the &ldquo;Last Updated&rdquo; date at the
                    top of this page and, where appropriate, through direct notification to registered
                    users. Your continued use of the System after any such modifications constitutes
                    your acceptance of the revised Terms. We encourage you to review these Terms
                    periodically to stay informed of any updates.
                  </p>
                </div>
              </article>

              {/* 11. Contact Information */}
              <article id="contact" className="scroll-mt-24 overflow-hidden rounded-lg border border-[#015289]/20 shadow-sm">
                <div className="bg-[#015289] px-6 py-4 md:px-8">
                  <div className="flex items-center gap-3">
                    <span className="material-symbols-outlined text-2xl text-white">mail</span>
                    <h2 className="font-headline text-xl font-extrabold text-white md:text-2xl">11. Contact Information</h2>
                  </div>
                </div>
                <div className="bg-surface-container-lowest p-6 md:p-8">
                  <p className="mb-5 text-sm leading-relaxed text-on-surface-variant md:text-[15px]">
                    If you have any questions, concerns, or requests regarding these Terms of Service,
                    please contact the DMW Region VII office:
                  </p>
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {[
                      ['apartment', 'DMW Regional Office VII', 'G/F DMW Building, Kaohsiung corner Taiwan Streets corner, Cebu City, 6000 Philippines'],
                      ['call', 'Hotline', '(032) 268-8566 | 1348 (OFW Hotline)'],
                      ['email', 'Email', 'support@bayanihan.gov.ph'],
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
                </div>
              </article>

              {/* Footer note */}
              <div className="mt-8 rounded-lg border border-outline-variant/20 bg-surface-container p-6 text-center">
                <p className="text-xs leading-relaxed text-on-surface/50">
                  These Terms of Service are drafted in compliance with Republic Act No. 10173 (Data Privacy Act
                  of 2012), Republic Act No. 11641 (DMW Act), and other applicable Philippine laws.
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
