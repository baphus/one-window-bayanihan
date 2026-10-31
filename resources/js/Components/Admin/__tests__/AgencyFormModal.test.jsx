import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import AgencyFormModal from '../AgencyFormModal';

globalThis.route = (name) => `/mocked/${name}`;

const mocks = vi.hoisted(() => ({ transform: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  useForm: (initial) => ({
    data: initial,
    setData: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    transform: mocks.transform,
    processing: false,
    errors: {},
    setError: vi.fn(),
    clearErrors: vi.fn(),
  }),
}));
vi.mock('@/Hooks/useClientValidation', () => ({ default: () => ({ validate: () => true }) }));
vi.mock('@/Components/AgencyMapView', () => ({ default: () => null }));
vi.mock('../LogoUpload', () => ({ default: () => null }));
vi.mock('@/Components/InputError', () => ({ default: () => null }));

describe('AgencyFormModal contact parser', () => {
  it('omits structured fields on unchanged edits so legacy text survives backend combining', () => {
    const contact_info = 'Office hours: weekdays\nPhone: 555-0100';
    const { container } = render(<AgencyFormModal agency={{ id: 'agency-id', contact_info }} onClose={vi.fn()} />);
    fireEvent.submit(container.querySelector('form'));
    const transform = mocks.transform.mock.calls.at(-1)[0];
    expect(transform({ name: 'Office', contact_info, contact_address: contact_info, contact_tel: '', contact_email: '' }))
      .toEqual({ name: 'Office', contact_info });
  });
  it('loads Tel and retains multiline structured values', () => {
    render(<AgencyFormModal agency={{
      contact_info: 'Address: 123 Main Street\nSecond floor\nTel: 555-0100\nEmail: office@example.test',
    }} onClose={vi.fn()} />);

    const fields = screen.getAllByRole('textbox');
    expect(fields[3]).toHaveValue('123 Main Street\nSecond floor');
    expect(fields[4]).toHaveValue('555-0100');
    expect(fields[5]).toHaveValue('office@example.test');
  });

  it.each([
    'Legacy prefix\nAddress: 123 Main Street\nTel: 555-0100',
    'Address: 123 Main Street\nTel: 555-0100\nOffice hours: weekdays',
    'Address: First address\nAddress: Second address\nTel: 555-0100',
    'Address: 123 Main Street\nTel: First phone\nTel: Second phone',
  ])('preserves ambiguous legacy contact text unchanged', (contact_info) => {
    render(<AgencyFormModal agency={{ contact_info }} onClose={vi.fn()} />);

    expect(screen.getAllByRole('textbox')[3]).toHaveValue(contact_info);
  });
});
