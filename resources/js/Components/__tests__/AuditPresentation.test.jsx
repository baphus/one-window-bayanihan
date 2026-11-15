import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { AuditLogRow } from '@/lib/audit';
import { AuditTimeline } from '../AuditTimeline';

const changes = [
    { field: 'status', fieldLabel: 'Status', old: 'Pending', new: 'Approved' },
    { field: 'date_of_birth', fieldLabel: 'Date of birth', old: 'Value unavailable (historical encrypted data)', new: null },
    { field: 'title', fieldLabel: 'Title', new: 'A'.repeat(300) },
    { field: 'sex', fieldLabel: 'Sex', old: '', new: 'Not set' },
];
const log = { id: '1', action: 'CHANGE_PASSWORD', module: 'user', formatted_module: 'User', actor: 'Staff member', timestamp: '2026-01-01T08:00:00Z', message: 'Staff member changed their password', changes };

describe('audit presentation', () => {
    it('shows all admin changes through a keyboard-accessible disclosure', async () => {
        const user = userEvent.setup();
        render(<AuditLogRow log={log} showChanges />);
        expect(screen.getByText('change password')).toBeInTheDocument();
        const summary = screen.getByText('View changes (4)');
        const details = summary.closest('details');
        expect(details).not.toHaveAttribute('open');
        await user.click(summary);
        expect(details).toHaveAttribute('open');
        expect(screen.getByText('Date of birth')).toBeVisible();
        expect(details).toHaveTextContent('Before: Value unavailable (historical encrypted data)');
        expect(details).toHaveTextContent('After: Not set');
        expect(details).toHaveTextContent('Before: Not recorded');
        expect(details).toHaveTextContent('A'.repeat(300));
        expect(details).toHaveTextContent('Before: Not set');
    });

    it('keeps staff timeline summary-only even if changes are supplied', () => {
        render(<AuditTimeline logs={[log]} showFilters={false} />);
        expect(screen.getByText('Staff member changed their password')).toBeInTheDocument();
        expect(screen.queryByText(/View changes/)).not.toBeInTheDocument();
        expect(screen.queryByText('Date of birth')).not.toBeInTheDocument();
    });
});
