import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { UnifiedTable } from '../UnifiedTable.jsx';

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
