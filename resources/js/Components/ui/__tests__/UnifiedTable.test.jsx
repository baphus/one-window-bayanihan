import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { UnifiedTable } from '../UnifiedTable.jsx';

const rows = [
  { id: 'one', name: 'One', created_at: '2026-01-02T00:00:00Z' },
  { id: 'two', name: 'Two', created_at: '2026-01-01T00:00:00Z' },
];

const columns = [
  { key: 'name', title: 'Name' },
  { key: 'created_at', title: 'Created At' },
];

function renderTable(props = {}) {
  return render(
    <UnifiedTable
      data={rows}
      columns={columns}
      keyExtractor={(row) => row.id}
      {...props}
    />,
  );
}

describe('UnifiedTable collection contract', () => {
  it('does not expose search or pagination when a plain collection has no server capabilities', () => {
    render(
      <UnifiedTable
        data={[{ id: 'one', name: 'One' }]}
        columns={[{ key: 'name', title: 'Name', sortable: false }]}
        keyExtractor={(row) => row.id}
        hideSearch
        hidePagination
      />,
    );

    expect(screen.getByText('One')).toBeInTheDocument();
    expect(screen.queryByPlaceholderText('Search records...')).not.toBeInTheDocument();
    expect(screen.queryByText(/Showing .* of .* records/)).not.toBeInTheDocument();
  });
});

/**
 * The register restyle changes presentation only. These assertions describe
 * the behavior the visual layer must keep, so a class change can never quietly
 * remove a sort affordance, a tour hook, or the busy lifecycle.
 */
describe('UnifiedTable sort contract', () => {
  it('exposes every sortable header by an accessible button name', () => {
    renderTable({ onSortChange: vi.fn() });

    expect(screen.getByRole('button', { name: /Name/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Created At/ })).toBeInTheDocument();
  });

  it('marks sortable headers with aria-sort and leaves the unsorted one as none', () => {
    renderTable({ onSortChange: vi.fn(), sortKey: 'name', sortDirection: 'asc' });

    const nameHeader = screen.getByRole('columnheader', { name: /Name/ });
    const createdHeader = screen.getByRole('columnheader', { name: /Created At/ });

    expect(nameHeader).toHaveAttribute('aria-sort', 'ascending');
    expect(createdHeader).toHaveAttribute('aria-sort', 'none');
  });

  it('flips aria-sort to descending when the active column is sorted descending', () => {
    renderTable({ onSortChange: vi.fn(), sortKey: 'name', sortDirection: 'desc' });

    expect(screen.getByRole('columnheader', { name: /Name/ })).toHaveAttribute(
      'aria-sort',
      'descending',
    );
  });

  it('reports the first click on a fresh sortable column as ascending', () => {
    const onSortChange = vi.fn();
    renderTable({ onSortChange });

    fireEvent.click(screen.getByRole('button', { name: /Name/ }));

    expect(onSortChange).toHaveBeenCalledWith('name', 'asc');
  });

  it('defaults an untouched date-like column to descending', () => {
    renderTable({ onSortChange: vi.fn() });

    expect(screen.getByRole('columnheader', { name: /Created At/ })).toHaveAttribute(
      'aria-sort',
      'descending',
    );
  });

  it('does not offer a sort button on a column marked unsortable', () => {
    renderTable({
      onSortChange: vi.fn(),
      columns: [{ key: 'name', title: 'Name', sortable: false }],
    });

    expect(screen.queryByRole('button', { name: /Name/ })).not.toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: /Name/ })).toBeInTheDocument();
  });
});

describe('UnifiedTable reset sort visibility', () => {
  it('hides Reset Sort while the table sits on its baseline sort', () => {
    renderTable({ onSortChange: vi.fn() });

    expect(screen.queryByRole('button', { name: /reset sort/i })).not.toBeInTheDocument();
  });

  it('hides Reset Sort when a page pins the current sort through sortKey', () => {
    renderTable({ onSortChange: vi.fn(), sortKey: 'created_at', sortDirection: 'desc' });

    expect(screen.queryByRole('button', { name: /reset sort/i })).not.toBeInTheDocument();
  });

  it('shows Reset Sort once the sort moves away from the baseline', () => {
    renderTable({ onSortChange: vi.fn(), sortKey: 'name', sortDirection: 'asc' });

    expect(screen.getByRole('button', { name: /reset sort/i })).toBeInTheDocument();
  });

  it('honours an explicit defaultSortKey when deciding resettability', () => {
    renderTable({
      onSortChange: vi.fn(),
      defaultSortKey: 'created_at',
      defaultSortDirection: 'desc',
      sortKey: 'created_at',
      sortDirection: 'desc',
    });

    expect(screen.queryByRole('button', { name: /reset sort/i })).not.toBeInTheDocument();
  });

  it('stays hidden for a page that opts out with showSortReset={false}', () => {
    renderTable({
      onSortChange: vi.fn(),
      sortKey: 'name',
      sortDirection: 'asc',
      showSortReset: false,
    });

    expect(screen.queryByRole('button', { name: /reset sort/i })).not.toBeInTheDocument();
  });

  it('resets back to the baseline sort when clicked', () => {
    const onSortChange = vi.fn();
    renderTable({
      onSortChange,
      defaultSortKey: 'created_at',
      defaultSortDirection: 'desc',
      sortKey: 'name',
      sortDirection: 'asc',
    });

    fireEvent.click(screen.getByRole('button', { name: /reset sort/i }));

    expect(onSortChange).toHaveBeenCalledWith('created_at', 'desc');
  });
});

describe('UnifiedTable filter panel contract', () => {
  it('opens the filter panel as a labelled dialog', () => {
    renderTable({
      onAdvancedFilters: vi.fn(),
      isAdvancedFiltersOpen: true,
      advancedFiltersContent: <p>Filter body</p>,
    });

    expect(screen.getByRole('dialog', { name: /filters/i })).toBeInTheDocument();
    expect(screen.getByText('Filter body')).toBeInTheDocument();
  });

  it('does not render the panel while closed', () => {
    renderTable({
      onAdvancedFilters: vi.fn(),
      isAdvancedFiltersOpen: false,
      advancedFiltersContent: <p>Filter body</p>,
    });

    expect(screen.queryByRole('dialog', { name: /filters/i })).not.toBeInTheDocument();
  });

  it('asks the page to close the panel on Escape', () => {
    const onAdvancedFilters = vi.fn();
    renderTable({
      onAdvancedFilters,
      isAdvancedFiltersOpen: true,
      advancedFiltersContent: <p>Filter body</p>,
    });

    fireEvent.keyDown(document, { key: 'Escape' });

    expect(onAdvancedFilters).toHaveBeenCalled();
  });
});

describe('UnifiedTable busy lifecycle', () => {
  it('keeps aria-busy observable on the table container', () => {
    const { container, rerender } = renderTable({ isLoading: false });
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'false');

    rerender(
      <UnifiedTable
        data={rows}
        columns={columns}
        keyExtractor={(row) => row.id}
        isLoading
      />,
    );

    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'true');
  });

  it('announces loading to assistive technology while busy', () => {
    renderTable({ isLoading: true });

    expect(screen.getByRole('status')).toBeInTheDocument();
  });
});

describe('UnifiedTable control contract', () => {
  it('labels the search field and the clear affordance', () => {
    const onSearchClear = vi.fn();
    renderTable({
      searchValue: 'abc',
      searchPlaceholder: 'Search records...',
      onSearchChange: vi.fn(),
      onSearchClear,
    });

    expect(screen.getByRole('searchbox', { name: /search/i })).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /clear search/i }));
    expect(onSearchClear).toHaveBeenCalled();
  });

  it('labels the filters and columns controls', () => {
    renderTable({
      onAdvancedFilters: vi.fn(),
      onColumnsControl: vi.fn(),
    });

    expect(screen.getByRole('button', { name: /filters/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /columns/i })).toBeInTheDocument();
  });

  it('keeps the new-record label the caller passes', () => {
    const onNewRecord = vi.fn();
    renderTable({ onNewRecord, newRecordLabel: 'Create Case' });

    const button = screen.getByRole('button', { name: /create case/i });
    fireEvent.click(button);

    expect(onNewRecord).toHaveBeenCalled();
  });
});

describe('UnifiedTable selection contract', () => {
  it('renders no checkboxes unless the table is selectable', () => {
    renderTable({ onSelectionChange: vi.fn() });

    expect(screen.queryAllByRole('checkbox')).toHaveLength(0);
  });

  it('keeps selection controlled and reports the toggled row key', () => {
    const onSelectionChange = vi.fn();
    renderTable({
      selectable: true,
      selectedKeys: [],
      onSelectionChange,
    });

    const [, firstRowCheckbox] = screen.getAllByRole('checkbox');
    fireEvent.click(firstRowCheckbox);

    expect(onSelectionChange).toHaveBeenCalledWith(['one']);
  });

  it('reflects the selected keys it is given', () => {
    renderTable({
      selectable: true,
      selectedKeys: ['two'],
      onSelectionChange: vi.fn(),
    });

    const [, , secondRowCheckbox] = screen.getAllByRole('checkbox');
    expect(secondRowCheckbox).toBeChecked();
  });

  it('labels select-all and per-row checkboxes', () => {
    renderTable({ selectable: true, selectedKeys: [], onSelectionChange: vi.fn() });

    expect(screen.getByRole('checkbox', { name: /select all/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /one/i })).toBeInTheDocument();
  });
});

describe('UnifiedTable pagination contract', () => {
  it('keeps the record range string and the rows-per-page control', () => {
    const { container } = renderTable({
      currentPage: 1,
      totalPages: 3,
      totalRecords: 24,
      startIndex: 1,
      endIndex: 10,
      rowsPerPage: 10,
      onPageChange: vi.fn(),
      onRowsPerPageChange: vi.fn(),
    });

    expect(container.textContent).toContain('Showing 1–10 of 24 records');
    expect(screen.getByRole('combobox', { name: /rows per page/i })).toBeInTheDocument();
  });

  it('marks the current page and keeps page buttons named', () => {
    renderTable({
      currentPage: 2,
      totalPages: 3,
      totalRecords: 24,
      startIndex: 11,
      endIndex: 20,
      rowsPerPage: 10,
      onPageChange: vi.fn(),
    });

    expect(screen.getByRole('button', { name: 'Page 2' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('button', { name: 'Page 1' })).not.toHaveAttribute('aria-current');
  });

  it('disables the previous control on the first page and the next on the last', () => {
    renderTable({
      currentPage: 1,
      totalPages: 1,
      totalRecords: 2,
      startIndex: 1,
      endIndex: 2,
      rowsPerPage: 10,
      onPageChange: vi.fn(),
    });

    expect(screen.getByRole('button', { name: /previous page/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /next page/i })).toBeDisabled();
  });

  it('pages through the callback when a page number is clicked', () => {
    const onPageChange = vi.fn();
    renderTable({
      currentPage: 1,
      totalPages: 3,
      totalRecords: 24,
      startIndex: 1,
      endIndex: 10,
      rowsPerPage: 10,
      onPageChange,
    });

    fireEvent.click(screen.getByRole('button', { name: 'Page 3' }));
    expect(onPageChange).toHaveBeenCalledWith(3);
  });
});

describe('UnifiedTable empty states', () => {
  it('shows the caller empty-state message when there is no data and no query', () => {
    render(
      <UnifiedTable
        data={[]}
        columns={columns}
        keyExtractor={(row) => row.id}
        emptyStateMessage="No cases found"
      />,
    );

    expect(screen.getByText('No cases found')).toBeInTheDocument();
  });

  it('shows the no-results copy when a search is active', () => {
    render(
      <UnifiedTable
        data={[]}
        columns={columns}
        keyExtractor={(row) => row.id}
        searchValue="zzz"
        onSearchChange={vi.fn()}
      />,
    );

    expect(screen.getByText(/no results found/i)).toBeInTheDocument();
  });
});
