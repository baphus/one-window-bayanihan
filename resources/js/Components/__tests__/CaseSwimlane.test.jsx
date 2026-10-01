import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import CaseSwimlane, {
    buildSwimlaneLayout,
    formatSwimlaneDuration,
    formatTickLabel,
    generateTimeTicks,
    SWIMLANE_MIN_SEGMENT_PX,
} from '../CaseSwimlane';

const DAY = 86400000;

function makePayload(overrides = {}) {
    return {
        caseOpenedAt: '2026-01-01T00:00:00Z',
        caseClosedAt: null,
        generatedAt: '2026-02-01T00:00:00Z',
        referrals: [
            {
                id: 'r-late',
                agency: 'Late Agency',
                service: 'Legal aid',
                status: 'PENDING',
                statusLabel: 'Sent to agency — awaiting response',
                sentAt: '2026-01-15T00:00:00Z',
                isTerminal: false,
                terminalAt: null,
                segmentCount: 1,
                milestoneCount: 0,
                segments: [
                    { status: 'PENDING', label: 'Sent', start: '2026-01-15T00:00:00Z', end: null, milestoneCount: 0, isOpen: true },
                ],
                milestones: [],
            },
            {
                id: 'r-early',
                agency: 'Early Agency',
                service: 'Shelter',
                status: 'COMPLETED',
                statusLabel: 'Completed',
                sentAt: '2026-01-02T00:00:00Z',
                isTerminal: true,
                terminalAt: '2026-01-20T00:00:00Z',
                segmentCount: 2,
                milestoneCount: 1,
                segments: [
                    { status: 'PENDING', label: 'Sent', start: '2026-01-02T00:00:00Z', end: '2026-01-10T00:00:00Z', milestoneCount: 1, isOpen: false },
                    { status: 'PROCESSING', label: 'Processing', start: '2026-01-10T00:00:00Z', end: '2026-01-20T00:00:00Z', milestoneCount: 0, isOpen: false },
                ],
                milestones: [{ at: '2026-01-05T00:00:00Z', title: 'Accepted', description: 'Agency accepted' }],
            },
        ],
        caseManagerLane: {
            segments: [
                { status: 'CASE_MANAGER', label: 'Review', start: '2026-01-20T00:00:00Z', end: null, milestoneCount: 0, isOpen: true },
            ],
            receivedCount: 1,
            readyToClose: false,
            closedAt: null,
        },
        totals: { referrals: 2, active: 1, terminal: 1, milestones: 1 },
        ...overrides,
    };
}

describe('buildSwimlaneLayout', () => {
    it('positions segments proportionally to elapsed time', () => {
        // Domain: Jan 1 (caseOpenedAt) → Feb 1 (generatedAt) = 31 days, track 3100px = 100px/day.
        const layout = buildSwimlaneLayout(makePayload(), { width: 3100 });
        expect(layout.trackWidth).toBe(3100);
        expect(layout.domainStartIso).toBe('2026-01-01T00:00:00.000Z');
        expect(layout.domainEndIso).toBe('2026-02-01T00:00:00.000Z');

        const early = layout.lanes.find((lane) => lane.id === 'r-early');
        const first = early.segments[0]; // Jan 2 → Jan 10 (8 days)
        expect(first.x / 3100).toBeCloseTo(1 / 31, 3);
        expect(first.width / 3100).toBeCloseTo(8 / 31, 3);
    });

    it('floors very short segments to the minimum width', () => {
        const payload = makePayload();
        payload.referrals[1].segments.push({
            status: 'PROCESSING',
            label: 'Brief',
            start: '2026-01-10T00:00:00Z',
            end: '2026-01-10T02:00:00Z', // two hours inside a 31-day span ≈ 2.8px raw
            milestoneCount: 0,
            isOpen: false,
        });
        const layout = buildSwimlaneLayout(payload, { width: 1000 });
        const brief = layout.lanes
            .find((lane) => lane.id === 'r-early')
            .segments.find((segment) => segment.label === 'Brief');
        expect(brief.width).toBeGreaterThanOrEqual(SWIMLANE_MIN_SEGMENT_PX);
        expect(brief.width).toBe(SWIMLANE_MIN_SEGMENT_PX);
    });

    it('gives every referral exactly one lane in ascending sentAt order', () => {
        const layout = buildSwimlaneLayout(makePayload(), { width: 1000 });
        expect(layout.lanes).toHaveLength(2);
        // Input order is late-first; layout must sort earliest-sent first.
        expect(layout.lanes.map((lane) => lane.id)).toEqual(['r-early', 'r-late']);
    });

    it('produces a convergence anchor for a terminal referral with terminalAt', () => {
        const layout = buildSwimlaneLayout(makePayload(), { width: 1000 });
        expect(layout.convergence).toHaveLength(1);
        expect(layout.convergence[0].laneIndex).toBe(0);
        const expectedX = ((Date.parse('2026-01-20T00:00:00Z') - Date.parse('2026-01-01T00:00:00Z')) / (31 * DAY)) * 1000;
        expect(layout.convergence[0].x).toBeCloseTo(expectedX, 1);
    });

    it('falls back to generatedAt when a segment is still open', () => {
        const payload = makePayload({ caseOpenedAt: null });
        // Latest end is open (null) → domain end must be generatedAt.
        const layout = buildSwimlaneLayout(payload, { width: 800 });
        expect(layout.domainEndIso).toBe('2026-02-01T00:00:00.000Z');
    });

    it('returns null for null/undefined payloads', () => {
        expect(buildSwimlaneLayout(null)).toBeNull();
        expect(buildSwimlaneLayout(undefined)).toBeNull();
    });

    it('never emits more ticks than fit legibly', () => {
        const layout = buildSwimlaneLayout(makePayload(), { width: 620 });
        expect(layout.ticks.length).toBeGreaterThan(1);
        expect(layout.ticks.length).toBeLessThanOrEqual(Math.floor(620 / 90) + 1);
    });
});

describe('tick + duration formatters', () => {
    it('labels ticks by span length', () => {
        expect(formatTickLabel(Date.UTC(2026, 0, 5, 14), 2 * DAY)).toContain('Jan 5');
        expect(formatTickLabel(Date.UTC(2026, 0, 5), 60 * DAY)).toBe('Jan 5');
        expect(formatTickLabel(Date.UTC(2026, 5, 1), 400 * DAY)).toBe('Jun 2026');
        expect(formatTickLabel(Date.UTC(2026, 5, 1), 900 * DAY)).toBe('2026');
    });

    it('generates ticks inside the domain', () => {
        const start = Date.UTC(2026, 0, 1);
        const end = Date.UTC(2026, 1, 1);
        const ticks = generateTimeTicks(start, end, 800);
        expect(ticks.length).toBeGreaterThan(0);
        for (const tick of ticks) {
            expect(tick.value).toBeGreaterThanOrEqual(start);
            expect(tick.value).toBeLessThanOrEqual(end);
        }
    });

    it('formats durations readably', () => {
        expect(formatSwimlaneDuration(45 * 60000)).toBe('45 min');
        expect(formatSwimlaneDuration(3 * 3600000)).toBe('3 hr');
        expect(formatSwimlaneDuration(12 * DAY)).toBe('12 days');
    });
});

describe('CaseSwimlane rendering', () => {
    it('renders the empty state for null payloads without throwing', () => {
        const { container } = render(<CaseSwimlane swimlaneTimeline={null} width={800} />);
        expect(container.querySelector('[data-testid="swimlane-empty"]')).not.toBeNull();
        expect(screen.getByText(/No timeline data yet/i)).toBeInTheDocument();
    });

    it('renders a distinct no-referrals state for an empty referrals list', () => {
        const { container } = render(<CaseSwimlane swimlaneTimeline={makePayload({ referrals: [] })} width={800} />);
        expect(container.querySelector('[data-testid="swimlane-empty"]')).not.toBeNull();
        expect(screen.getByText(/No referrals yet/i)).toBeInTheDocument();
    });

    it('renders one lane per referral plus the manager lane', () => {
        const { container } = render(<CaseSwimlane swimlaneTimeline={makePayload()} width={800} />);
        expect(container.querySelectorAll('[data-testid="swimlane-lane"]')).toHaveLength(2);
        expect(container.querySelector('[data-testid="swimlane-manager-lane"]')).not.toBeNull();
        expect(container.querySelectorAll('[data-testid="swimlane-convergence"]')).toHaveLength(1);
        expect(screen.getByText('Early Agency')).toBeInTheDocument();
        expect(screen.getByText('Late Agency')).toBeInTheDocument();
    });

    it('falls back when agency is null', () => {
        const payload = makePayload();
        payload.referrals[0].agency = null;
        render(<CaseSwimlane swimlaneTimeline={payload} width={800} />);
        expect(screen.getByText('Agency not specified')).toBeInTheDocument();
    });

    it('gives segments accessible labels with agency, status and duration', () => {
        const { container } = render(<CaseSwimlane swimlaneTimeline={makePayload()} width={800} />);
        const segments = Array.from(container.querySelectorAll('[data-testid="swimlane-segment"]'));
        expect(segments.length).toBeGreaterThan(0);
        for (const segment of segments) {
            expect(segment.getAttribute('aria-label')).toMatch(/—/);
        }
    });

    it('shows the ready-to-close state instead of just another bar', () => {
        const payload = makePayload();
        payload.caseManagerLane.readyToClose = true;
        render(<CaseSwimlane swimlaneTimeline={payload} width={800} />);
        expect(screen.getAllByText('Ready to close').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText(/All referrals are resolved/)).toBeInTheDocument();
    });

    it('shows the closed state when caseClosedAt is set', () => {
        const payload = makePayload({ caseClosedAt: '2026-02-01T00:00:00Z' });
        render(<CaseSwimlane swimlaneTimeline={payload} width={800} />);
        expect(screen.getAllByText('Case closed').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText(/Closed February/)).toBeInTheDocument();
    });
});
