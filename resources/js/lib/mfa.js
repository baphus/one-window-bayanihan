export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/** True while the five-minute fresh-MFA window is still open. */
export function isFreshMfa(confirmedUntil) {
    if (!confirmedUntil) return false;
    const until = new Date(confirmedUntil).getTime();
    return Number.isFinite(until) && until > Date.now();
}
