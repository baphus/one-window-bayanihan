{/* ⚠️ AI-DRAFTED CONTENT — Requires legal review before production deployment */}
import { Head, Link, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';

export default function TermsOfService() {
  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Terms of Service" />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1">
        <section className="relative flex min-h-[300px] w-full items-center justify-center overflow-hidden bg-[#0b5c92]">
          <div className="absolute inset-0 bg-gradient-to-br from-[#0b5c92] via-[#0b5c92]/90 to-blue-800/30"></div>
          <div className="relative z-10 mx-auto w-full max-w-7xl px-4 text-center md:px-8">
            <h1 className="mb-4 font-headline text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
              Terms of Service
            </h1>
            <p className="mx-auto max-w-2xl text-lg leading-relaxed text-white/80">
              Please read these terms carefully before using the Bayanihan One Window system.
            </p>
          </div>
        </section>

        <section className="py-16 px-4">
          <div className="prose prose-slate mx-auto max-w-3xl">
            <p className="lead font-bold text-slate-800">
              Last Updated: June 1, 2026
            </p>

            <h2>Acceptance of Terms</h2>
            <p>
              By accessing or using the Bayanihan One Window system (the "System"), operated by the
              Department of Migrant Workers (DMW) Region VII, you agree to be bound by these Terms
              of Service (the "Terms"). If you do not agree to all of these Terms, you are not
              authorized to access or use the System. These Terms constitute a legally binding
              agreement between you and the DMW Region VII governing your use of the System and
              any services provided therein.
            </p>

            <h2>Description of Service</h2>
            <p>
              The Bayanihan One Window system is a centralized digital case management platform
              designed to streamline the processing, tracking, and referral of concerns involving
              Overseas Filipino Workers (OFWs) and their families. The System facilitates
              coordination among DMW, OWWA, DOLE, DOH, DSWD, TESDA, and other partner government
              agencies to ensure timely and efficient delivery of assistance and services. The
              System provides case registration, document management, referral tracking, and
              communication tools for authorized personnel and stakeholders.
            </p>

            <h2>User Obligations</h2>
            <p>As a user of the System, you agree to:</p>
            <ul>
              <li>
                <strong>Provide Accurate Information:</strong> You must provide true, accurate,
                current, and complete information when registering for an account or submitting
                case-related data. Knowingly submitting false or misleading information may result
                in account suspension and referral to appropriate legal authorities.
              </li>
              <li>
                <strong>Use Lawfully:</strong> You shall use the System only for lawful purposes
                and in accordance with these Terms. You may not use the System for any fraudulent,
                abusive, or otherwise illegal activity.
              </li>
              <li>
                <strong>Maintain Confidentiality:</strong> You are responsible for maintaining the
                confidentiality of your account credentials, including your username and password.
                You must notify DMW Region VII immediately of any unauthorized use of your account
                or any other breach of security.
              </li>
              <li>
                <strong>Comply with Applicable Laws:</strong> You shall comply with all applicable
                Philippine laws and regulations, including but not limited to the Data Privacy Act
                of 2012 (Republic Act No. 10173) and the Department of Migrant Workers Act
                (Republic Act No. 11641).
              </li>
            </ul>

            <h2>Account Registration</h2>
            <p>
              Access to certain features of the System requires account registration. The following
              categories of users may register for accounts:
            </p>
            <ul>
              <li>
                <strong>Case Managers:</strong> DMW personnel authorized to create, process, and
                manage case records within the System.
              </li>
              <li>
                <strong>Agency Staff:</strong> Personnel from partner government agencies authorized
                to receive referrals, update case statuses, and coordinate with DMW on shared cases.
              </li>
              <li>
                <strong>Administrators:</strong> Authorized system administrators with elevated
                privileges to manage user accounts, system settings, and audit trails.
              </li>
            </ul>
            <p>
              All account registrations are subject to approval by DMW Region VII. The DMW reserves
              the right to deny, suspend, or terminate any account at its sole discretion and
              without prior notice if it determines that the user has violated these Terms or poses
              a security risk to the System.
            </p>

            <h2>User Conduct</h2>
            <p>The following activities are strictly prohibited while using the System:</p>
            <ul>
              <li>
                <strong>Unauthorized Access:</strong> Attempting to access, probe, or connect to
                computing systems or accounts without authorization, including attempting to
                bypass authentication mechanisms or access data not intended for you.
              </li>
              <li>
                <strong>Data Misuse:</strong> Accessing, downloading, modifying, copying,
                distributing, or disclosing any data or information from the System without proper
                authorization or for purposes beyond the scope of your official duties.
              </li>
              <li>
                <strong>Fraudulent Activity:</strong> Submitting false or fabricated case
                information, documents, or evidence. Impersonating another person or entity, or
                misrepresenting your affiliation with any person or entity.
              </li>
              <li>
                <strong>Interference:</strong> Uploading or transmitting viruses, malware, or any
                other malicious code that may disrupt, damage, or impair the functionality of the
                System or any connected network.
              </li>
              <li>
                <strong>Commercial Exploitation:</strong> Using the System for commercial
                solicitation, advertising, or any activity that generates profit without the
                express written consent of DMW Region VII.
              </li>
            </ul>

            <h2>Intellectual Property</h2>
            <p>
              The System, including its design, code, databases, interfaces, logos, and all
              related intellectual property, is owned by the Department of Migrant Workers or its
              licensors. No part of the System may be reproduced, distributed, modified, or
              otherwise used without prior written authorization. Users retain ownership of any
              content, documents, or data they lawfully submit to the System, but grant DMW a
              non-exclusive, royalty-free license to use, store, and process such content for the
              purposes of operating the System and delivering services to the public.
            </p>

            <h2>Limitation of Liability</h2>
            <p>
              The Bayanihan One Window system is provided on an &ldquo;as is&rdquo; and
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

            <h2>Privacy</h2>
            <p>
              Your use of the System is subject to our{' '}
              <Link href={route('privacy')} className="text-[#0b5c92] font-semibold hover:underline">
                Privacy Policy
              </Link>
              , which describes how we collect, use, store, and protect your personal information.
              By using the System, you consent to the data practices described in the Privacy
              Policy. Please review it carefully to understand our commitment to protecting your
              privacy and complying with the Data Privacy Act of 2012 (Republic Act No. 10173).
            </p>

            <h2>Governing Law</h2>
            <p>
              These Terms shall be governed by and construed in accordance with the laws of the
              Republic of the Philippines. Any disputes arising out of or relating to these Terms
              or your use of the System shall be resolved exclusively in the appropriate courts
              of Cebu City, Philippines. This provision applies notwithstanding any conflict of
              law principles. The following laws are particularly relevant to these Terms:
            </p>
            <ul>
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

            <h2>Changes to Terms</h2>
            <p>
              DMW Region VII reserves the right to modify, amend, or update these Terms at any
              time and without prior individual notice. Material changes will be communicated
              through the System, including by updating the &ldquo;Last Updated&rdquo; date at the
              top of this page and, where appropriate, through direct notification to registered
              users. Your continued use of the System after any such modifications constitutes
              your acceptance of the revised Terms. We encourage you to review these Terms
              periodically to stay informed of any updates.
            </p>

            <h2>Contact Information</h2>
            <p>
              If you have any questions, concerns, or requests regarding these Terms of Service,
              please contact the DMW Region VII office:
            </p>
            <p>
              <strong>DMW Regional Office VII</strong><br />
              G/F DMW Building, Kaohsiung corner Taiwan Streets corner<br />
              Cebu City, 6000 Philippines<br />
              <strong>Hotline:</strong> (032) 268-8566 | 1348 (OFW Hotline)<br />
              <strong>Email:</strong> support@bayanihan.gov.ph
            </p>
          </div>
        </section>
      </main>

      <AppFooter />
    </div>
  );
}
