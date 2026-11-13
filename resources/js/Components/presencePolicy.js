export function canShowCasePresence(role, roles) {
    return role === roles?.CASE_MANAGER || role === roles?.ADMIN || role === roles?.AGENCY;
}

export function canShowReferralPresence(user, referral, roles) {
    const staff = [roles?.CASE_MANAGER, roles?.ADMIN, roles?.AGENCY].includes(user?.role);
    return staff && Boolean(user?.agcy_id) && String(user.agcy_id) === String(referral?.agcy_id);
}
