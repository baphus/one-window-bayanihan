export * from './types';
export { caseManagerTour } from './configs/caseManager';
export { agencyTour } from './configs/agency';
export { adminTour } from './configs/admin';

import { TourConfig } from './types';
import { caseManagerTour } from './configs/caseManager';
import { agencyTour } from './configs/agency';
import { adminTour } from './configs/admin';

/** Get the tour config for a given role */
export function getTourConfig(role: string): TourConfig | null {
    const configs: Record<string, TourConfig> = {
        CASE_MANAGER: caseManagerTour,
        AGENCY: agencyTour,
        ADMIN: adminTour,
    };
    return configs[role] ?? null;
}
