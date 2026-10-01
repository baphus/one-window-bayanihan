import { render, screen, within } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import UnifiedTimeline, { sortTimelineItems } from '../Timeline';

const OLDEST = { id: 'oldest', date: '2026-01-01T08:00:00Z', title: 'First event', type: 'case_opened' };
const MIDDLE = { id: 'middle', date: '2026-02-01T08:00:00Z', title: 'Middle event', type: 'milestone_added' };
const NEWEST = { id: 'newest', date: '2026-03-01T08:00:00Z', title: 'Latest event', type: 'case_closed' };

describe('UnifiedTimeline bottom-up ordering', () => {
    it('sorts items newest-first even when passed oldest-first', () => {
        expect(sortTimelineItems([OLDEST, MIDDLE, NEWEST]).map((i) => i.id)).toEqual([
            'newest',
            'middle',
            'oldest',
        ]);
    });

    it('keeps newest-first order when passed newest-first (no caller reverse needed)', () => {
        expect(sortTimelineItems([NEWEST, MIDDLE, OLDEST]).map((i) => i.id)).toEqual([
            'newest',
            'middle',
            'oldest',
        ]);
    });

    it('reads every supported date key', () => {
        const items = [
            { id: 'a', timestamp: '2026-01-10T00:00:00Z', title: 'A' },
            { id: 'b', created_at: '2026-03-10T00:00:00Z', title: 'B' },
            { id: 'c', createdAt: '2026-02-10T00:00:00Z', title: 'C' },
        ];
        expect(sortTimelineItems(items).map((i) => i.id)).toEqual(['b', 'c', 'a']);
    });

    it('renders the most recent event on top in the spine variant', () => {
        const { container } = render(<UnifiedTimeline items={[OLDEST, NEWEST, MIDDLE]} />);
        const headings = Array.from(container.querySelectorAll('h4')).map((h) => h.textContent);
        expect(headings).toEqual(['Latest event', 'Middle event', 'First event']);
    });

    it('renders the most recent entry on top in the ledger variant', () => {
        const { container } = render(<UnifiedTimeline variant="ledger" items={[OLDEST, NEWEST]} />);
        const titles = Array.from(container.querySelectorAll('li p.text-sm')).map((p) => p.textContent);
        expect(titles).toEqual(['Latest event', 'First event']);
    });

    it('shows the empty state when there are no items', () => {
        render(<UnifiedTimeline items={[]} emptyTitle="Nothing here yet." />);
        expect(screen.getByText('Nothing here yet.')).toBeInTheDocument();
    });

    it('renders footer actions such as an Add Milestone button', () => {
        render(
            <UnifiedTimeline
                items={[OLDEST]}
                footerActions={<button type="button">+ Add Milestone</button>}
            />,
        );
        expect(screen.getByRole('button', { name: '+ Add Milestone' })).toBeInTheDocument();
    });

    it('still honors the deprecated headerActions alias', () => {
        render(
            <UnifiedTimeline
                items={[OLDEST]}
                headerActions={<button type="button">+ Add Milestone</button>}
            />,
        );
        expect(screen.getByRole('button', { name: '+ Add Milestone' })).toBeInTheDocument();
    });

    it('renders audit cards newest-first through the plain variant', () => {        const { container } = render(
            <UnifiedTimeline
                variant="plain"
                items={[
                    { id: 'old', timestamp: '2026-01-01T00:00:00Z', details: 'Old change' },
                    { id: 'new', timestamp: '2026-05-01T00:00:00Z', details: 'New change' },
                ]}
                renderItem={({ item }) => <p>{item.details}</p>}
            />,
        );
        const rows = Array.from(container.querySelectorAll('p')).map((p) => p.textContent);
        expect(rows).toEqual(['New change', 'Old change']);
        expect(within(container).getByText('New change')).toBeInTheDocument();
    });

    it('sinks items with invalid dates to the bottom', () => {
        const items = [
            { id: 'broken', date: 'not-a-date', title: 'Broken' },
            { id: 'good', date: '2026-03-01T08:00:00Z', title: 'Good' },
        ];
        expect(sortTimelineItems(items).map((i) => i.id)).toEqual(['good', 'broken']);
    });

    it('preserves input order for equal timestamps', () => {
        const items = [
            { id: 'first-in', date: '2026-03-01T08:00:00Z', title: 'First in' },
            { id: 'second-in', date: '2026-03-01T08:00:00Z', title: 'Second in' },
        ];
        expect(sortTimelineItems(items).map((i) => i.id)).toEqual(['first-in', 'second-in']);
    });

    it("puts the oldest event first with sortOrder='asc'", () => {
        expect(sortTimelineItems([NEWEST, OLDEST, MIDDLE], 'asc').map((i) => i.id)).toEqual([
            'oldest',
            'middle',
            'newest',
        ]);
    });
});
