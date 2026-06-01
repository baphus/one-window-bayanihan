import { Head, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';

export default function Contact() {
  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Contact Us" />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1">
        <section className="relative flex min-h-[300px] w-full items-center justify-center overflow-hidden bg-[#0b5c92]">
          <div className="absolute inset-0 bg-gradient-to-br from-[#0b5c92] via-[#0b5c92]/90 to-blue-800/30"></div>
          <div className="relative z-10 mx-auto w-full max-w-7xl px-4 text-center md:px-8">
            <h1 className="mb-4 font-headline text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
              Contact Support
            </h1>
            <p className="mx-auto max-w-2xl text-lg leading-relaxed text-white/80">
              Get in touch with the Bayanihan One Window team for assistance with your concerns.
            </p>
          </div>
        </section>

        <section className="py-16 px-4">
          <div className="mx-auto max-w-7xl">
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
              <div className="space-y-8">
                <div>
                  <h2 className="text-xl font-bold text-slate-900 mb-6">Get in Touch</h2>
                  <div className="space-y-6">
                    <div className="flex items-start gap-4">
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 shrink-0">
                        <span className="material-symbols-outlined text-[#0b5c92]">location_on</span>
                      </div>
                      <div>
                        <h3 className="text-sm font-bold text-slate-900">Office Address</h3>
                        <p className="text-sm text-slate-600 mt-1">
                          DMW Regional Office VII<br />
                          G/F, DOLE Bldg. (Old Insular Bldg.), <br/> Gorordo, cor General Maxilom Ave, Cebu City
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-4">
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 shrink-0">
                        <span className="material-symbols-outlined text-[#0b5c92]">call</span>
                      </div>
                      <div>
                        <h3 className="text-sm font-bold text-slate-900">Hotline</h3>
                        <p className="text-sm text-slate-600 mt-1">
                          (032) 268-8566<br />
                          1348 (OFW Hotline)
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-4">
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 shrink-0">
                        <span className="material-symbols-outlined text-[#0b5c92]">mail</span>
                      </div>
                      <div>
                        <h3 className="text-sm font-bold text-slate-900">Email</h3>
                        <p className="text-sm text-slate-600 mt-1">
                          support@bayanihan.gov.ph<br />
                          owwa.cebu@owwa.gov.ph
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-4">
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 shrink-0">
                        <span className="material-symbols-outlined text-[#0b5c92]">schedule</span>
                      </div>
                      <div>
                        <h3 className="text-sm font-bold text-slate-900">Office Hours</h3>
                        <p className="text-sm text-slate-600 mt-1">
                          Monday to Friday: 8:00 AM - 5:00 PM<br />
                          Saturday: 8:00 AM - 12:00 PM
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-xl font-bold text-slate-900 mb-6">Send a Message</h2>
                <p className="text-sm text-slate-600 mb-6">
                  For case-specific inquiries, please use the{" "}
                  <a href={route('track.index')} className="text-[#0b5c92] font-semibold hover:underline">
                    Track Your Case
                  </a>{" "}
                  feature to check the status of your application.
                </p>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Your Name</label>
                    <input type="text" className="block w-full rounded-md border-slate-300 shadow-sm focus:border-[#0b5c92] focus:ring-[#0b5c92] text-sm" placeholder="Enter your name" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                    <input type="email" className="block w-full rounded-md border-slate-300 shadow-sm focus:border-[#0b5c92] focus:ring-[#0b5c92] text-sm" placeholder="Enter your email" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Message</label>
                    <textarea rows={5} className="block w-full rounded-md border-slate-300 shadow-sm focus:border-[#0b5c92] focus:ring-[#0b5c92] text-sm" placeholder="Describe your concern..." />
                  </div>
                  <button
                    type="button"
                    className="w-full rounded-md bg-[#0b5c92] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#0a4d7a] transition-colors"
                  >
                    Send Message
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
