import { Link } from '@inertiajs/react';
import AppButton from './AppButton';

export default function AppHeader({ onTrackCaseClick }) {
  return (
    <nav className="sticky top-0 z-50 w-full border-b border-gray-200 bg-white">
      <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-4 md:px-8">
        <Link href="/" className="flex items-center gap-3">
          <div className="flex h-[40px] w-[40px] items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-white">
            <span className="material-symbols-outlined text-[#005288] text-2xl">handshake</span>
          </div>
          <div className="flex flex-col">
            <span className="font-headline text-[18px] font-bold text-[#005288]">Bayanihan One Window</span>
            <span className="text-[12px] font-medium text-slate-500 uppercase tracking-wide">DMW Region VII</span>
          </div>
        </Link>

        <div className="hidden items-center gap-8 md:flex">
          <a href="#features" className="font-label text-[14px] font-medium text-slate-600 transition-colors duration-200 hover:text-[#005288]">Track Your Case</a>
          <a href="#partners" className="font-label text-[14px] font-medium text-slate-600 transition-colors duration-200 hover:text-[#005288]">Partner Agencies</a>
          <a href="#faq" className="font-label text-[14px] font-medium text-slate-600 transition-colors duration-200 hover:text-[#005288]">FAQ</a>
        </div>

        <div className="flex items-center gap-4">
          {onTrackCaseClick ? (
            <AppButton variant="primary" onClick={onTrackCaseClick}>Track Case</AppButton>
          ) : (
            <AppButton href="#tracker" variant="primary">Track Case</AppButton>
          )}
          <Link href={route('login')}>
            <AppButton variant="outline">Login</AppButton>
          </Link>
        </div>
      </div>
    </nav>
  );
}
