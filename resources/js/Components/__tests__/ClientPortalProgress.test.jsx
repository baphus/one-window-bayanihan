import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
    REFERRAL_STATUS_LABELS,
    getReferralStatusLabel,
    getReferralStamp,
} from '../ReferralStamp';
import ClientStepBar, { getStepProgress } from '../ClientStepBar';
import OfficeChecklist, {
    buildChecklistRows,
    getWaitingSinceIso,
    indexSwimlaneSentAt,
} from '../CaseProgressChecklist';
import ActionNeededBanner, {
    getBlockingReferrals,
    shouldShowActionBanner,
} from '../ActionNeededBanner';
import { getOfwCaseGroup, getOfwCaseStatusLabel } from '../ofwCaseStatus';
import {
    formatClientDateTime,
    formatClientLongDate,
    formatClientShortDate,
} from '../clientDates';

const AGENCIES = [
    { referralId: 'ref-1', name: 'Office of Workers Welfare', status: 'PROCESSING' },
    { referralId: 'ref-2', name: 'Legal Aid Bureau', status: 'FOR_COMPLIANCE' },
    { referralId: 'ref-3', name: 'Medical Mission', status: 'COMPLETED' },
];

const TIMELINE = [
    { referralId: 'ref-1', date: '2026-03-10T08:00:00Z', title: 'Referred', type: 'referral_sent' },
    { referralId: 'ref-1', date: '2026-03-12T08:00:00Z', title: 'Accepted', type: 'referral_status_changed' },
    { referralId: 'ref-2', date: '2026-04-01T08:00:00Z', title: 'Referred', type: 'referral_sent' },
    { referralId: 'ref-9', date: '2026-01-01T08:00:00Z', title: 'Other case', type: 'referral_sent' },
];

describe('ReferralStamp shared vocabulary', () => {
    it('covers all five referral states with humanized labels', () => {
        expect(REFERRAL_STATUS_LABELS).toEqual({
            PENDING: 'Awaiting receipt',
            PROCESSING: 'In process',
            FOR_COMPLIANCE: 'Needs documents',
            COMPLETED: 'Completed',
            REJECTED: 'Unable to assist',
        });
    });

    it('returns the humanized label per status and never a raw code', () => {
        for (const [status, label] of Object.entries(REFERRAL_STATUS_LABELS)) {
            expect(getReferralStatusLabel(status)).toBe(label);
            expect(getReferralStamp(status).label).toBe(label);
        }
        expect(getReferralStatusLabel('PENDING')).not.toBe('PENDING');
        expect(getReferralStatusLabel('FOR_COMPLIANCE')).not.toBe('FOR_COMPLIANCE');
    });

    it('falls back to Pending for unknown codes', () => {
        expect(getReferralStatusLabel('SOMETHING_NEW')).toBe('Awaiting receipt');
        expect(getReferralStamp(undefined).label).toBe('Awaiting receipt');
    });
});

describe('ClientStepBar progress math', () => {
    const steps = [
        { label: 'Created', state: 'complete' },
        { label: 'Referred', state: 'complete' },
        { label: 'Processing', state: 'active' },
        { label: 'Completed', state: 'pending' },
    ];

    it('finds the active step and its share of the track', () => {
        const { activeIndex, activeLabel, progressPercent } = getStepProgress(steps);
        expect(activeIndex).toBe(2);
        expect(activeLabel).toBe('Processing');
        expect(progressPercent).toBeCloseTo((2 / 3) * 100);
    });

    it('names the final step when everything is complete', () => {
        const done = steps.map((s) => ({ ...s, state: 'complete' }));
        const { activeIndex, activeLabel, progressPercent } = getStepProgress(done);
        expect(activeIndex).toBe(-1);
        expect(activeLabel).toBe('Completed');
        expect(progressPercent).toBe(100);
    });

    it('stays at zero for single-step and empty runs', () => {
        expect(getStepProgress([{ label: 'Only', state: 'active' }]).progressPercent).toBe(0);
        expect(getStepProgress([]).progressPercent).toBe(0);
        expect(getStepProgress([]).activeLabel).toBeNull();
    });

    it('exposes every step label to screen readers even when visually collapsed', () => {
        const { container } = render(<ClientStepBar steps={steps} />);
        const items = container.querySelectorAll('li');
        expect(items).toHaveLength(4);
        expect(items[2].getAttribute('aria-label')).toBe('Processing — current step');
        expect(items[0].getAttribute('aria-label')).toBe('Created — completed');
        expect(items[3].getAttribute('aria-label')).toBe('Completed — upcoming');
        // Visually-hidden labels carry the full sequence for SR users.
        expect(screen.getByText('Processing: current step', { selector: '.sr-only' })).toBeInTheDocument();
    });
});

describe('OfficeChecklist waiting-since derivation', () => {
    it('takes the earliest dated event per referral', () => {
        expect(getWaitingSinceIso('ref-1', TIMELINE)).toBe('2026-03-10T08:00:00.000Z');
        expect(getWaitingSinceIso('ref-2', TIMELINE)).toBe('2026-04-01T08:00:00.000Z');
    });

    it('returns null when nothing datable exists', () => {
        expect(getWaitingSinceIso('ref-3', TIMELINE)).toBeNull();
        expect(getWaitingSinceIso('ref-1', [])).toBeNull();
        expect(getWaitingSinceIso(null, TIMELINE)).toBeNull();
        expect(
            getWaitingSinceIso('ref-1', [{ referralId: 'ref-1', date: 'not-a-date', title: 'X' }]),
        ).toBeNull();
    });

    it('builds one row per agency and only dates active referrals', () => {
        const rows = buildChecklistRows(AGENCIES, TIMELINE);
        expect(rows).toHaveLength(3);
        expect(rows[0]).toMatchObject({
            name: 'Office of Workers Welfare',
            statusLabel: 'In process',
            waitingSinceIso: '2026-03-10T08:00:00.000Z',
        });
        expect(rows[1].statusLabel).toBe('Needs documents');
        // Terminal referrals simply end — no waiting-since line.
        expect(rows[2]).toMatchObject({ statusLabel: 'Completed', waitingSinceIso: null });
    });

    it('renders office names, humanized stamps, caption — and no raw codes', () => {
        render(
            <OfficeChecklist
                agencies={AGENCIES}
                milestoneTimeline={TIMELINE}
                caption="Last updated 02 April 2026 · 55% of processing complete"
                formatDate={() => '10 March 2026'}
            />,
        );
        expect(screen.getByText('Office of Workers Welfare')).toBeInTheDocument();
        expect(screen.getByText('In process')).toBeInTheDocument();
        expect(screen.getByText('Needs documents')).toBeInTheDocument();
        expect(screen.getAllByText(/Waiting since 10 March 2026/)).toHaveLength(2);
        expect(screen.getByText(/55% of processing complete/)).toBeInTheDocument();
        const list = screen.getByRole('list', { name: 'Progress by office' });
        expect(list.textContent).not.toMatch(/PROCESSING|FOR_COMPLIANCE|COMPLETED/);
        expect(within(list).getAllByRole('listitem')).toHaveLength(3);
    });

    it('renders nothing when there are no agencies', () => {
        const { container } = render(<OfficeChecklist agencies={[]} />);
        expect(container).toBeEmptyDOMElement();
    });
});

describe('OfficeChecklist swimlane sentAt anchor', () => {
    const SWIMLANE = {
        caseOpenedAt: '2026-01-05T08:00:00Z',
        resolvedAt: null,
        generatedAt: '2026-05-01T08:00:00Z',
        referrals: [
            { agency: 'Office of Workers Welfare', sentAt: '2026-02-01T08:00:00Z', statusLabel: 'In process', isCurrent: true },
            { agency: 'Legal Aid Bureau', sentAt: '2026-02-15T08:00:00Z', statusLabel: 'Needs documents', isCurrent: true },
            { agency: 'Medical Mission', sentAt: '2026-01-20T08:00:00Z', statusLabel: 'Completed', isCurrent: false },
        ],
        totals: { referrals: 3, active: 2, milestones: 4 },
    };

    it('prefers swimlane sentAt over the timeline-event date', () => {
        const rows = buildChecklistRows(AGENCIES, TIMELINE, SWIMLANE);
        // Timeline says 10 Mar for ref-1, but the truthful send instant wins.
        expect(rows[0].waitingSinceIso).toBe('2026-02-01T08:00:00.000Z');
        expect(rows[1].waitingSinceIso).toBe('2026-02-15T08:00:00.000Z');
        // Terminal rows still simply end, even with a sentAt present.
        expect(rows[2].waitingSinceIso).toBeNull();
    });

    it('degrades to timeline derivation when the prop is absent', () => {
        const rows = buildChecklistRows(AGENCIES, TIMELINE, null);
        expect(rows[0].waitingSinceIso).toBe('2026-03-10T08:00:00.000Z');
        expect(buildChecklistRows(AGENCIES, TIMELINE)).toEqual(
            buildChecklistRows(AGENCIES, TIMELINE, undefined),
        );
    });

    it('degrades per-row when the agency is unmatched or its sentAt is bad', () => {
        const partial = {
            ...SWIMLANE,
            referrals: [
                { agency: 'Some Other Office', sentAt: '2026-02-01T08:00:00Z' },
                { agency: 'Legal Aid Bureau', sentAt: 'not-a-date' },
            ],
        };
        const rows = buildChecklistRows(AGENCIES, TIMELINE, partial);
        // ref-1 unmatched by name → timeline fallback; ref-2 bad sentAt → fallback.
        expect(rows[0].waitingSinceIso).toBe('2026-03-10T08:00:00.000Z');
        expect(rows[1].waitingSinceIso).toBe('2026-04-01T08:00:00.000Z');
    });

    it('pairs duplicate agency names off in order instead of sharing one instant', () => {
        const dupes = [
            { referralId: 'a', name: 'Same Office', status: 'PROCESSING' },
            { referralId: 'b', name: 'Same Office', status: 'PENDING' },
        ];
        const swimlane = {
            referrals: [
                { agency: 'Same Office', sentAt: '2026-01-01T08:00:00Z' },
                { agency: 'Same Office', sentAt: '2026-03-01T08:00:00Z' },
            ],
        };
        const rows = buildChecklistRows(dupes, [], swimlane);
        expect(rows[0].waitingSinceIso).toBe('2026-01-01T08:00:00.000Z');
        expect(rows[1].waitingSinceIso).toBe('2026-03-01T08:00:00.000Z');
    });

    it('ignores nameless lanes and non-array payloads without crashing', () => {
        expect(indexSwimlaneSentAt(null).size).toBe(0);
        expect(indexSwimlaneSentAt({}).size).toBe(0);
        expect(indexSwimlaneSentAt({ referrals: 'nope' }).size).toBe(0);
        const queues = indexSwimlaneSentAt({ referrals: [{ sentAt: '2026-01-01T08:00:00Z' }] });
        expect(queues.size).toBe(0);
    });

    it('renders the sentAt-derived date in the component', () => {
        render(
            <OfficeChecklist
                agencies={AGENCIES}
                milestoneTimeline={TIMELINE}
                clientSwimlaneTimeline={SWIMLANE}
                formatDate={(iso) => `on ${iso.slice(0, 10)}`}
            />,
        );
        expect(screen.getByText('Waiting since on 2026-02-01')).toBeInTheDocument();
        expect(screen.getByText('Waiting since on 2026-02-15')).toBeInTheDocument();
    });
});

describe('ActionNeededBanner visibility rule', () => {
    it('flags FOR_COMPLIANCE referrals as blocking', () => {
        expect(getBlockingReferrals(AGENCIES)).toHaveLength(1);
        expect(getBlockingReferrals(AGENCIES)[0].name).toBe('Legal Aid Bureau');
        expect(getBlockingReferrals([])).toHaveLength(0);
    });

    it('shows for blocking referrals or an open request, hides otherwise', () => {
        expect(shouldShowActionBanner({ trackingAgencies: AGENCIES })).toBe(true);
        expect(
            shouldShowActionBanner({ trackingAgencies: [], hasOpenRequest: true }),
        ).toBe(true);
        expect(shouldShowActionBanner({ trackingAgencies: [] })).toBe(false);
        expect(
            shouldShowActionBanner({ trackingAgencies: [{ status: 'PROCESSING' }] }),
        ).toBe(false);
    });

    it('anchors to the blocking chapter and names the office', () => {
        render(<ActionNeededBanner agencies={AGENCIES} />);
        expect(screen.getByText(/Action needed/)).toBeInTheDocument();
        const jump = screen.getByRole('link', { name: /Jump to Legal Aid Bureau/ });
        expect(jump.getAttribute('href')).toBe('#agency-ref-2');
    });

    it('renders nothing when nothing needs action', () => {
        const { container } = render(<ActionNeededBanner agencies={[]} />);
        expect(container).toBeEmptyDOMElement();
    });
});

describe('OFW dashboard client-safe case vocabulary', () => {
    it('keeps self-filed drafts under review', () => {
        expect(getOfwCaseGroup('DRAFT', 'self_filed')).toBe('review');
        expect(getOfwCaseStatusLabel('DRAFT', 'self_filed')).toBe('Under Review');
    });

    it('treats any draft as under review (the default branch)', () => {
        expect(getOfwCaseStatusLabel('DRAFT', 'office_filed')).toBe('Under Review');
    });

    it('maps open-family codes to In Progress without leaking raw words', () => {
        for (const status of ['OPEN', 'PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'IN_PROGRESS', 'BEING_PREPARED']) {
            expect(getOfwCaseStatusLabel(status)).toBe('In Progress');
        }
        expect(getOfwCaseStatusLabel('OPEN')).not.toBe('Open');
    });

    it('maps closed-family codes to Resolved and archives to Archived', () => {
        for (const status of ['CLOSED', 'COMPLETED', 'RESOLVED']) {
            expect(getOfwCaseStatusLabel(status)).toBe('Resolved');
        }
        expect(getOfwCaseStatusLabel('ARCHIVED')).toBe('Archived');
    });

    it('degrades unknown codes to In Progress', () => {
        expect(getOfwCaseStatusLabel('SOMETHING_NEW')).toBe('In Progress');
    });
});

describe('clientDates shared formatters', () => {
    // Midday UTC keeps the calendar day stable across viewer timezones.
    const NOON = '2026-03-10T12:00:00Z';

    it('renders the long, short, and datetime shapes in en-PH', () => {
        expect(formatClientLongDate(NOON)).toMatch(/March/);
        expect(formatClientLongDate(NOON)).toMatch(/2026/);
        expect(formatClientShortDate(NOON)).toMatch(/Mar/);
        expect(formatClientShortDate(NOON)).toMatch(/2026/);
        expect(formatClientDateTime(NOON)).toMatch(/Mar/);
    });

    it('degrades to an em dash instead of "Invalid Date"', () => {
        for (const fn of [formatClientLongDate, formatClientShortDate, formatClientDateTime]) {
            expect(fn(null)).toBe('—');
            expect(fn(undefined)).toBe('—');
            expect(fn('')).toBe('—');
            expect(fn('not-a-date')).toBe('—');
        }
    });
});
