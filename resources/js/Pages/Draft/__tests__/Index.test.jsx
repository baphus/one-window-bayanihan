import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { post, visit } = vi.hoisted(() => ({ post: vi.fn(), visit: vi.fn() }));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/ui/ConfirmDialog', () => ({
  default: ({ open, onConfirm, confirmLabel, title }) =>
    open ? (
      <div role="dialog" aria-label={title}>
        <button type="button" onClick={onConfirm}>{confirmLabel}</button>
      </div>
    ) : null,
}));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Components/ui/RowContextMenu', () => ({
  RowContextMenu: ({ children }) => <div>{children}</div>,
  RowContextMenuItem: () => null,
}));
vi.mock('@/Components/ui/TableLoadingOverlay', () => ({ default: () => null }));
vi.mock('@/Hooks/useTableVisitLoading', () => ({ default: () => ({ isLoading: false, withLoading: (x) => x }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }) }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, router: { post, visit, get: vi.fn(), delete: vi.fn() } }));

globalThis.route = (name, id) => id ? `/cases/${id}/${name.split('.').at(-1)}` : `/${name}`;

import DraftIndex from '../Index';

const draft = { id: 'draft-123', case_number: 'CASE-1', client_type: 'OFW', draft_client_data: { first_name: 'A', last_name: 'B' }, updated_at: new Date().toISOString() };
const props = { drafts: { data: [draft], current_page: 1, last_page: 1 }, filters: {} };

describe('draft index publish characterization', () => {
  beforeEach(() => vi.clearAllMocks());

  it('asks for confirmation before publishing and posts on confirm', () => {
    render(<DraftIndex {...props} />);

    fireEvent.click(screen.getByRole('button', { name: 'Publish' }));

    expect(post).not.toHaveBeenCalled();

    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Publish' }));

    expect(post).toHaveBeenCalledWith('/cases/draft-123/publish', {}, expect.objectContaining({ onFinish: expect.any(Function) }));
  });
});
