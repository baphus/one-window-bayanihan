import { useState } from 'react';
import { FAQ_CATEGORIES, FAQ_ITEMS } from './faqData';
import useInView from '@/Hooks/useInView';

export default function FaqSection() {
  const [openItemId, setOpenItemId] = useState(FAQ_ITEMS[0]?.id ?? null);
  const [headingRef, headingVisible] = useInView();
  const [listRef, listVisible] = useInView();

  return (
    <section id="faq" className="bg-surface px-8 py-24">
      <div className="mx-auto max-w-5xl">
        <div ref={headingRef} className={`mb-12 text-center owb-reveal ${headingVisible ? 'is-visible' : ''}`}>
          <h2 className="font-headline text-2xl font-extrabold text-primary md:text-3xl">Frequently Asked Questions</h2>
          <p className="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-on-surface-variant md:text-base">
            Get fast answers about tracking, agency coordination, and privacy.
          </p>
        </div>
        <div ref={listRef} className="space-y-8 owb-stagger">
          {FAQ_CATEGORIES.map((cat) => {
            const items = FAQ_ITEMS.filter((i) => i.category === cat);
            if (!items.length) return null;
            return (
              <section key={cat} className={`owb-reveal ${listVisible ? 'is-visible' : ''}`}>
                <h3 className="mb-3 text-sm font-extrabold uppercase tracking-[0.08em] text-secondary md:text-[13px]">{cat}</h3>
                <div className="space-y-4">
                  {items.map((item) => {
                    const isOpen = item.id === openItemId;
                    return (
                      <article key={item.id} className="border border-outline-variant/40 bg-surface-container-lowest shadow-sm">
                        <button type="button" onClick={() => setOpenItemId(isOpen ? null : item.id)}
                          aria-expanded={isOpen}
                          className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors hover:bg-surface-container">
                          <span className="text-sm font-bold text-primary md:text-base">{item.question}</span>
                          <span className="material-symbols-outlined text-primary" aria-hidden="true">{isOpen ? 'remove' : 'add'}</span>
                        </button>
                        <div className={`grid transition-[grid-template-rows] duration-200 ease-out ${isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                          <div className="overflow-hidden">
                            <div className="border-t border-outline-variant/30 px-5 py-4">
                              <p className="text-sm leading-relaxed text-on-surface-variant md:text-[15px]">{item.answer}</p>
                            </div>
                          </div>
                        </div>
                      </article>
                    );
                  })}
                </div>
              </section>
            );
          })}
        </div>
      </div>
    </section>
  );
}
