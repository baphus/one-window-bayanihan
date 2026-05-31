{/* ⚠️ AI-DRAFTED CONTENT — Requires legal review before production deployment */}
{/* This Privacy Policy references RA 10173 (Data Privacy Act of 2012) and DMW Act RA 11641 */}
{/* Review and adapt to actual data processing practices before going live */}

import { Head, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';

export default function PrivacyPolicy() {
  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Privacy Policy" />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1">
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

        <section className="py-16 px-4">
          <div className="prose prose-slate mx-auto max-w-3xl">
            <p className="text-sm font-bold uppercase tracking-wide text-slate-500">
              Last Updated: June 1, 2026
            </p>

            <h2>1. Introduction</h2>
            <p>
              The Bayanihan One Window System (&ldquo;we,&rdquo; &ldquo;us,&rdquo; or &ldquo;our&rdquo;) is the
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

            <h2>2. Information We Collect</h2>
            <p>We collect the following categories of personal data to process and manage your concerns:</p>
            <ul>
              <li>
                <strong>Personal Identifiers:</strong> Full name, date of birth, place of birth, gender,
                civil status, and nationality.
              </li>
              <li>
                <strong>Contact Information:</strong> Home address, mobile number, email address, and
                emergency contact details.
              </li>
              <li>
                <strong>Government-Issued IDs:</strong> Passport number, Philippine Passport details,
                Unified Multi-Purpose ID (UMID), Philippine Identification System (PhilSys ID) number,
                and other valid government-issued identification documents.
              </li>
              <li>
                <strong>Employment and Case Data:</strong> Employment contract details, employer information,
                POEA/DMW processing records, case history, referrals, supporting documents, and
                correspondence related to your case.
              </li>
              <li>
                <strong>Technical Data:</strong> IP address, browser type, device information, and usage
                analytics collected automatically when you access the platform.
              </li>
            </ul>

            <h2>3. How We Use Your Information</h2>
            <p>
              We process your personal data solely for the following legitimate purposes:
            </p>
            <ul>
              <li>
                <strong>Case Management:</strong> To receive, process, track, and resolve your concerns,
                complaints, or requests filed through the Bayanihan One Window System.
              </li>
              <li>
                <strong>Referrals and Coordination:</strong> To refer your case to the appropriate partner
                agency (OWWA, DOLE, DSWD, TESDA, DOH, POEA, or other government entities) when
                necessary for case resolution.
              </li>
              <li>
                <strong>Legal Compliance:</strong> To comply with legal obligations under RA 10173,
                RA 11641 (DMW Act), and other applicable laws, rules, and regulations.
              </li>
              <li>
                <strong>Reporting and Analytics:</strong> To generate anonymized statistical reports for
                program improvement, policy development, and public accountability.
              </li>
              <li>
                <strong>Communication:</strong> To contact you regarding the status of your case,
                requests for additional information, and other case-related matters.
              </li>
            </ul>

            <h2>4. Legal Basis for Processing</h2>
            <p>
              Our processing of your personal data is grounded on the following legal bases under the
              Data Privacy Act of 2012:
            </p>
            <ul>
              <li>
                <strong>Consent:</strong> Where you have voluntarily provided your personal data and
                explicitly consented to its processing for the purposes stated in this policy.
              </li>
              <li>
                <strong>Contractual Necessity:</strong> Processing is necessary for the performance of
                a contract to which you are a party, including the filing and management of your case.
              </li>
              <li>
                <strong>Legal Obligation:</strong> Processing is required by law, including our mandate
                under <strong>Republic Act No. 11641</strong> (Department of Migrant Workers Act), which
                establishes DMW as the lead agency for the protection of OFW rights and welfare.
              </li>
              <li>
                <strong>Public Interest:</strong> Processing is necessary for the performance of a task
                carried out in the public interest, specifically the delivery of migrant worker services
                and the administration of government programs for OFWs.
              </li>
            </ul>

            <h2>5. Information Sharing and Disclosure</h2>
            <p>
              Your personal data may be shared with the following government partner agencies strictly on a
              need-to-know basis and only to the extent necessary for case resolution:
            </p>
            <ul>
              <li>Overseas Workers Welfare Administration (OWWA)</li>
              <li>Department of Labor and Employment (DOLE)</li>
              <li>Department of Social Welfare and Development (DSWD)</li>
              <li>Technical Education and Skills Development Authority (TESDA)</li>
              <li>Department of Health (DOH)</li>
              <li>Philippine Overseas Employment Administration (POEA)</li>
            </ul>
            <p>
              We do <strong>not</strong> sell, trade, or rent your personal data to third parties for
              commercial purposes. Disclosure to law enforcement or regulatory bodies shall only occur
              when required by law or pursuant to a valid legal order.
            </p>

            <h2>6. Data Security</h2>
            <p>
              We implement appropriate organizational, physical, and technical security measures to protect
              your personal data against unauthorized access, alteration, disclosure, or destruction. These
              measures include:
            </p>
            <ul>
              <li>
                <strong>Encryption:</strong> All data transmitted between your device and our servers is
                encrypted using industry-standard TLS protocols. Data at rest is encrypted using approved
                cryptographic algorithms.
              </li>
              <li>
                <strong>Access Controls:</strong> Role-based access controls ensure that only authorized
                personnel have access to personal data, and only to the extent required for their
                official functions.
              </li>
              <li>
                <strong>Regular Audits:</strong> We conduct periodic security audits, vulnerability
                assessments, and penetration testing to identify and address potential risks.
              </li>
              <li>
                <strong>Data Processing Agreements:</strong> All third-party service providers who
                process data on our behalf are bound by contractual obligations to maintain the
                confidentiality and security of personal data.
              </li>
            </ul>

            <h2>7. Data Retention</h2>
            <p>
              We retain your personal data only for as long as necessary to fulfill the purposes for which
              it was collected, or as required by applicable laws and regulations. Retention periods are
              determined in accordance with:
            </p>
            <ul>
              <li>The Data Privacy Act of 2012 (RA 10173) and its IRR</li>
              <li>The Department of Migrant Workers Act (RA 11641)</li>
              <li>National Archives of the Philippines (NAP) retention schedules for government records</li>
              <li>Other relevant issuances by the National Privacy Commission (NPC)</li>
            </ul>
            <p>
              Once the retention period expires, personal data shall be anonymized, archived, or securely
              disposed of in a manner that prevents further processing or unauthorized access.
            </p>

            <h2>8. Your Rights Under the Data Privacy Act</h2>
            <p>
              As a data subject, you have the following rights under RA 10173, which we respect and
              facilitate:
            </p>
            <ul>
              <li>
                <strong>Right to be Informed:</strong> To be informed of the collection and processing
                of your personal data, including the purposes and legal bases thereof.
              </li>
              <li>
                <strong>Right to Access:</strong> To access and obtain a copy of your personal data
                held by our system.
              </li>
              <li>
                <strong>Right to Rectification:</strong> To request the correction of any inaccurate
                or incomplete personal data.
              </li>
              <li>
                <strong>Right to Erasure or Blocking:</strong> To request the suspension, withdrawal,
                or removal of your personal data from our files, subject to legal retention
                requirements.
              </li>
              <li>
                <strong>Right to Object:</strong> To object to the processing of your personal data,
                including processing for direct marketing or automated decision-making.
              </li>
              <li>
                <strong>Right to Data Portability:</strong> To receive your personal data in a
                commonly used, machine-readable format and to transmit such data to another
                processing system.
              </li>
              <li>
                <strong>Right to Damages:</strong> To claim compensation for damages sustained due to
                violation of your data privacy rights.
              </li>
            </ul>
            <p>
              To exercise any of these rights, please contact our Data Protection Officer using the
              contact details provided below.
            </p>

            <h2>9. Changes to This Privacy Policy</h2>
            <p>
              We reserve the right to update or modify this Privacy Policy at any time. Changes will
              take effect immediately upon posting on this page. We encourage you to review this
              Privacy Policy periodically to stay informed about how we are protecting your data.
              Material changes will be communicated through our platform or via the contact
              information you have provided.
            </p>

            <h2>10. Contact Information</h2>
            <p>
              For questions, concerns, or requests regarding this Privacy Policy or the processing of
              your personal data, you may reach us through the following:
            </p>
            <ul>
              <li>
                <strong>Data Protection Officer:</strong> DMW Region VII — Data Privacy Office
              </li>
              <li>
                <strong>Office Address:</strong> DMW Regional Office VII, G/F DMW Building,
                Kaohsiung corner Taiwan Streets, Cebu City, 6000 Philippines
              </li>
              <li>
                <strong>Email:</strong> dpo@bayanihan.gov.ph
              </li>
              <li>
                <strong>Hotline:</strong> (032) 268-8566 | 1348 (OFW Hotline)
              </li>
              <li>
                <strong>National Privacy Commission:</strong> You may also file a complaint with the
                National Privacy Commission at <strong>npc.gov.ph</strong> or at (02) 8234-2222,
                should you find that your data privacy rights have been violated.
              </li>
            </ul>

            <hr className="my-10" />

            <p className="text-sm text-slate-400 italic">
              This Privacy Policy is drafted in compliance with Republic Act No. 10173 (Data Privacy Act
              of 2012) and its Implementing Rules and Regulations. For the full text of the law, visit
              the official website of the National Privacy Commission at www.privacy.gov.ph.
            </p>
          </div>
        </section>
      </main>

      <AppFooter />
    </div>
  );
}
