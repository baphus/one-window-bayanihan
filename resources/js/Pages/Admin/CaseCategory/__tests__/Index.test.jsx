import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import CategoryIndex from '../Index.jsx';
import IssueIndex from '../../CaseIssue/Index.jsx';

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, router: { get: vi.fn(), delete: vi.fn(), patch: vi.fn() } }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: () => null }));
vi.mock('@/Components/Admin/CaseCategoryFormModal', () => ({ default: () => null }));
vi.mock('@/Components/Admin/CaseIssueFormModal', () => ({ default: () => null }));

describe('case classification tables', () => {
  it('keeps unsupported controls out of plain category collections', () => {
    render(<CategoryIndex categories={[{ id: 'cat-1', name: 'Welfare', is_active: true, sort_order: 1 }]} />);

    expect(screen.getByText('Welfare')).toBeInTheDocument();
    expect(screen.queryByPlaceholderText('Search records...')).not.toBeInTheDocument();
    expect(screen.queryByText(/Showing .* of .* records/)).not.toBeInTheDocument();
  });

  it('keeps unsupported controls out of plain issue collections while retaining the status action', () => {
    render(<IssueIndex issues={[{ id: 'issue-1', name: 'Contract dispute', is_active: true, sort_order: 1 }]} filters={{}} />);

    expect(screen.getByText('Contract dispute')).toBeInTheDocument();
    expect(screen.getByText('Show deactivated')).toBeInTheDocument();
    expect(screen.queryByPlaceholderText('Search records...')).not.toBeInTheDocument();
    expect(screen.queryByText(/Showing .* of .* records/)).not.toBeInTheDocument();
  });
});
