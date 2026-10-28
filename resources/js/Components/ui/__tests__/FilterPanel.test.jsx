import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import FilterPanel from '../FilterPanel.jsx';

const groups = [
  {
    key: 'status',
    label: 'Status',
    options: [
      { value: 'OPEN', label: 'Open' },
      { value: 'CLOSED', label: 'Closed' },
    ],
  },
  {
    key: 'client_type',
    label: 'Client Type',
    options: [
      { value: 'OFW', label: 'OFW' },
      { value: 'NEXT_OF_KIN', label: 'Next of Kin' },
    ],
  },
];

function renderPanel(props = {}) {
  return render(
    <FilterPanel
      groups={groups}
      selected={{}}
      onToggle={vi.fn()}
      onClearAll={vi.fn()}
      activeCount={0}
      {...props}
    />,
  );
}

describe('FilterPanel checkbox groups', () => {
  it('renders every option as a checkbox, not a select', () => {
    renderPanel();

    expect(screen.getAllByRole('checkbox')).toHaveLength(4);
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
  });

  it('names each checkbox by its option label alone', () => {
    renderPanel();

    expect(screen.getByRole('checkbox', { name: 'Open' })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'Next of Kin' })).toBeInTheDocument();
  });

  it('reflects the selected values it is given', () => {
    renderPanel({ selected: { status: ['OPEN'] } });

    expect(screen.getByRole('checkbox', { name: 'Open' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Closed' })).not.toBeChecked();
  });

  it('renders single groups as one accessible radio group', () => {
    renderPanel({
      groups: [{
        key: 'account_status',
        label: 'Account',
        single: true,
        options: [
          { value: 'linked', label: 'With account' },
          { value: 'no_account', label: 'No account' },
        ],
      }],
      selected: { account_status: ['linked'] },
    });

    const radios = screen.getAllByRole('radio');
    expect(radios).toHaveLength(2);
    expect(screen.getByRole('radio', { name: 'With account' })).toBeChecked();
    expect(screen.getByRole('radio', { name: 'No account' })).not.toBeChecked();
    expect(radios[0]).toHaveAttribute('name', radios[1].getAttribute('name'));
    expect(screen.queryAllByRole('checkbox')).toHaveLength(0);
  });

  it('reports the toggled value with its group key', () => {
    const onToggle = vi.fn();
    renderPanel({ onToggle });

    fireEvent.click(screen.getByRole('checkbox', { name: 'Closed' }));

    expect(onToggle).toHaveBeenCalledWith('status', 'CLOSED');
  });

  it('keeps groups in one vertical stack', () => {
    const { container } = renderPanel();

    const fieldsets = container.querySelectorAll('fieldset');
    expect(fieldsets).toHaveLength(2);
    // Every group wrapper is a direct child of the same column container.
    const groupsColumn = fieldsets[0].parentElement.parentElement;
    expect(fieldsets[1].parentElement.parentElement).toBe(groupsColumn);
    expect(groupsColumn.className).toContain('flex-col');
  });
});

describe('FilterPanel search', () => {
  it('filters options by label', () => {
    renderPanel({ searchable: true });

    fireEvent.change(screen.getByRole('searchbox', { name: /search filter options/i }), {
      target: { value: 'clo' },
    });

    expect(screen.getByRole('checkbox', { name: 'Closed' })).toBeInTheDocument();
    expect(screen.queryByRole('checkbox', { name: 'Open' })).not.toBeInTheDocument();
  });

  it('hides a group entirely when none of its options match', () => {
    renderPanel({ searchable: true });

    fireEvent.change(screen.getByRole('searchbox', { name: /search filter options/i }), {
      target: { value: 'zzz' },
    });

    expect(screen.queryAllByRole('checkbox')).toHaveLength(0);
    expect(screen.getByText(/no filters match/i)).toBeInTheDocument();
  });
});

describe('FilterPanel clear all', () => {
  it('disables Clear all when nothing is applied', () => {
    renderPanel({ activeCount: 0 });

    expect(screen.getByRole('button', { name: /clear all/i })).toBeDisabled();
  });

  it('reports the active count and clears when clicked', () => {
    const onClearAll = vi.fn();
    renderPanel({ activeCount: 2, onClearAll });

    expect(screen.getByText('2 active')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /clear all/i }));

    expect(onClearAll).toHaveBeenCalled();
  });
});

describe('FilterPanel collapsible groups', () => {
  const manyOptions = [
    {
      key: 'category_ids',
      label: 'Category',
      options: Array.from({ length: 8 }, (_, index) => ({
        value: `c${index}`,
        label: `Category ${index}`,
      })),
    },
  ];

  it('starts a long group collapsed behind a disclosure', () => {
    render(
      <FilterPanel
        groups={manyOptions}
        selected={{}}
        onToggle={vi.fn()}
        onClearAll={vi.fn()}
        activeCount={0}
      />,
    );

    const disclosure = screen.getByRole('button', { name: /category/i });
    expect(disclosure).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryAllByRole('checkbox')).toHaveLength(0);
  });

  it('reveals the options when the disclosure is opened', () => {
    render(
      <FilterPanel
        groups={manyOptions}
        selected={{}}
        onToggle={vi.fn()}
        onClearAll={vi.fn()}
        activeCount={0}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /category/i }));

    expect(screen.getByRole('button', { name: /category/i })).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getAllByRole('checkbox')).toHaveLength(8);
  });

  it('shows matching options even while a collapsed group is closed', () => {
    render(
      <FilterPanel
        groups={manyOptions}
        selected={{}}
        onToggle={vi.fn()}
        onClearAll={vi.fn()}
        activeCount={0}
        searchable
      />,
    );

    fireEvent.change(screen.getByRole('searchbox', { name: /search filter options/i }), {
      target: { value: 'Category 3' },
    });

    expect(screen.getByRole('checkbox', { name: 'Category 3' })).toBeInTheDocument();
  });
});
