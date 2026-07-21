import { useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';
import InputError from '@/Components/InputError';
import TurnstileWidget from '@/Components/TurnstileWidget';
import useInView from '@/Hooks/useInView';

export default function Contact() {
  const { turnstile } = usePage().props;
  const [turnstileToken, setTurnstileToken] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [contentRef, contentVisible] = useInView();

  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    email: '',
    message: '',
    cf_turnstile_response: '',
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    data.cf_turnstile_response = turnstileToken;
    post(route('contact.store'), {
      preserveScroll: true,
      onSuccess: () => {
        setSubmitted(true);
        reset();
        setTurnstileToken('');
      },
    });
  };

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Contact Us" />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1 pt-20">
        <section className="relative flex min-h-[300px] w-full items-center justify-center overflow-hidden bg-[#0b5c92]">
          <div className="absolute inset-0 bg-gradient-to-br from-[#0b5c92] via-[#0b5c92]/90 to-blue-800/30"></div>
          <div className="relative z-10 mx-auto w-full max-w-7xl px-4 text-center md:px-8">
            <h1 className="mb-4 font-headline text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
              Contact Support
            </h1>
            <p className="mx-auto max-w-2xl text-lg leading-relaxed text-white/80">
              Get in touch with the One Window Bayanihan team for assistance with your concerns.
            </p>
          </div>
        </section>

        <section className="py-16 px-4">
          <div className="mx-auto max-w-7xl">
            <div ref={contentRef} className={`grid grid-cols-1 gap-8 lg:grid-cols-2 owb-reveal ${contentVisible ? 'is-visible' : ''}`}>
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
                {submitted ? (
                  <div className="text-center py-8">
                    <div className="mx-auto mb-4 w-14 h-14 rounded-full bg-green-100 flex items-center justify-center">
                      <span className="material-symbols-outlined text-green-600 text-3xl">check_circle</span>
                    </div>
                    <h2 className="text-xl font-bold text-slate-900 mb-2">Message Sent!</h2>
                    <p className="text-sm text-slate-600 mb-6">
                      Thank you for reaching out. We'll get back to you as soon as possible.
                    </p>
                    <button
                      type="button"
                      onClick={() => setSubmitted(false)}
                      className="text-sm font-semibold text-[#0b5c92] hover:underline"
                    >
                      Send another message
                    </button>
                  </div>
                ) : (
                  <>
                    <h2 className="text-xl font-bold text-slate-900 mb-6">Send a Message</h2>
                    <p className="text-sm text-slate-600 mb-6">
                      For case-specific inquiries, please use the{" "}
                      <a href={route('track.index')} className="text-[#0b5c92] font-semibold hover:underline">
                        Track Your Case
                      </a>{" "}
                      feature to check the status of your application.
                    </p>
                    <form onSubmit={handleSubmit} className="space-y-4">
                      <div>
                        <label htmlFor="contact-name" className="block text-sm font-medium text-slate-700 mb-1">
                          Your Name
                        </label>
                        <input
                          id="contact-name"
                          type="text"
                          value={data.name}
                          onChange={(e) => setData('name', e.target.value)}
                          className="block w-full rounded-md border-slate-300 shadow-sm focus:border-[#0b5c92] focus:ring-[#0b5c92] text-sm"
                          placeholder="Enter your name"
                          required
                        />
                        <InputError message={errors.name} className="mt-1" />
                      </div>
                      <div>
                        <label htmlFor="contact-email" className="block text-sm font-medium text-slate-700 mb-1">
                          Email Address
                        </label>
                        <input
                          id="contact-email"
                          type="email"
                          value={data.email}
                          onChange={(e) => setData('email', e.target.value)}
                          className="block w-full rounded-md border-slate-300 shadow-sm focus:border-[#0b5c92] focus:ring-[#0b5c92] text-sm"
                          placeholder="Enter your email"
                          required
                        />
                        <InputError message={errors.email} className="mt-1" />
                      </div>
                      <div>
                        <label htmlFor="contact-message" className="block text-sm font-medium text-slate-700 mb-1">
                          Message
                        </label>
                        <textarea
                          id="contact-message"
                          rows={5}
                          value={data.message}
                          onChange={(e) => setData('message', e.target.value)}
                          className="block w-full rounded-md border-slate-300 shadow-sm focus:border-[#0b5c92] focus:ring-[#0b5c92] text-sm"
                          placeholder="Describe your concern..."
                          maxLength={2000}
                          required
                        />
                        <div className="flex justify-between items-start mt-1">
                          <InputError message={errors.message} />
                          <span className="text-xs text-slate-400">{data.message.length}/2000</span>
                        </div>
                      </div>
                      {turnstile?.enabled && (
                        <div className="text-center">
                          <TurnstileWidget onToken={setTurnstileToken} onExpire={() => setTurnstileToken('')} />
                          <InputError message={errors.captcha} className="mt-1" />
                        </div>
                      )}
                      <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-md bg-[#0b5c92] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#0a4d7a] transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                      >
                        {processing ? (
                          <>
                            <svg className="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Sending...
                          </>
                        ) : (
                          'Send Message'
                        )}
                      </button>
                    </form>
                  </>
                )}
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
