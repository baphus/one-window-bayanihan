import { useState } from 'react';

export default function AgencyLogo({ name, logoUrl, className = 'h-10 w-10' }) {
    const [failedUrl, setFailedUrl] = useState(null);

    return (
        <span className={`grid shrink-0 place-items-center rounded-lg bg-white text-primary ${className}`}>
            {logoUrl && failedUrl !== logoUrl ? (
                <img src={logoUrl} alt={`${name} logo`} className="h-full w-full object-contain" referrerPolicy="no-referrer" onError={() => setFailedUrl(logoUrl)} />
            ) : (
                <span className="material-symbols-outlined text-[24px]" aria-hidden="true">account_balance</span>
            )}
        </span>
    );
}
