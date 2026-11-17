import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AdministrativeExport from '../AdministrativeExport';

describe('AdministrativeExport', () => {
  beforeEach(() => {
    vi.stubGlobal('route', vi.fn(() => '/reports/administrative-export'));
    vi.stubGlobal('fetch', vi.fn());
  });
  afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

  it('requires independent dates and sends only export scope', async () => {
    fetch.mockResolvedValue({ ok: false, json: async () => ({ message: 'Narrow dates or agency.' }) });
    render(<AdministrativeExport role="ADMIN" agencyOptions={[{ value: 'agency-a', label: 'Agency A' }]} />);
    fireEvent.click(screen.getByText('Full Administrative Export'));
    const submit = screen.getByRole('button', { name: 'Download administrative XLSX' });
    expect(submit.disabled).toBe(true);
    fireEvent.change(screen.getByLabelText('Export start date'), { target: { value: '2026-01-01' } });
    fireEvent.change(screen.getByLabelText('Export end date'), { target: { value: '2026-01-31' } });
    fireEvent.change(screen.getByLabelText('Export agency'), { target: { value: 'agency-a' } });
    fireEvent.click(submit);
    await waitFor(() => expect(screen.getByRole('status').textContent).toContain('Narrow dates or agency.'));
    expect(route).toHaveBeenCalledWith('admin.exports.administrative', { from: '2026-01-01', to: '2026-01-31', agency_id: 'agency-a' });
    expect(submit.disabled).toBe(false);
  });

  it('hides export for non-admin roles', () => {
    render(<AdministrativeExport role="CASE_MANAGER" />);
    expect(screen.queryByText('Full Administrative Export')).toBeNull();
  });

  it('downloads a valid workbook and restores controls', async () => {
    const blob = { slice: () => ({ arrayBuffer: async () => new Uint8Array([0x50, 0x4b, 0x03, 0x04]).buffer }) };
    fetch.mockResolvedValue({ ok: true, headers: new Headers({ 'content-type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), blob: async () => blob });
    const create = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:workbook');
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    render(<AdministrativeExport role="ADMIN" />);
    fireEvent.click(screen.getByText('Full Administrative Export'));
    fireEvent.change(screen.getByLabelText('Export start date'), { target: { value: '2026-01-01' } });
    fireEvent.change(screen.getByLabelText('Export end date'), { target: { value: '2026-01-31' } });
    fireEvent.click(screen.getByRole('button', { name: 'Download administrative XLSX' }));
    await waitFor(() => expect(screen.getByRole('status').textContent).toContain('Workbook generated.'));
    expect(click).toHaveBeenCalledOnce();
    expect(create).toHaveBeenCalledWith(blob);
    expect(screen.getByRole('button', { name: 'Download administrative XLSX' }).disabled).toBe(false);
    create.mockRestore();
    click.mockRestore();
  });

  it('locks controls during generation and shows empty-result feedback', async () => {
    let finish;
    fetch.mockReturnValue(new Promise((resolve) => { finish = resolve; }));
    render(<AdministrativeExport role="ADMIN" />);
    fireEvent.click(screen.getByText('Full Administrative Export'));
    fireEvent.change(screen.getByLabelText('Export start date'), { target: { value: '2026-01-01' } });
    fireEvent.change(screen.getByLabelText('Export end date'), { target: { value: '2026-01-31' } });
    fireEvent.click(screen.getByRole('button', { name: 'Download administrative XLSX' }));
    expect(screen.getByRole('button', { name: 'Generating workbook…' }).disabled).toBe(true);
    expect(screen.getByLabelText('Export start date').disabled).toBe(true);
    expect(screen.getByLabelText('Export agency').disabled).toBe(true);
    finish({ ok: false, json: async () => ({ message: 'No cases match selected scope.' }) });
    await waitFor(() => expect(screen.getByRole('status').textContent).toContain('No cases match selected scope.'));
    expect(screen.getByRole('button', { name: 'Download administrative XLSX' }).disabled).toBe(false);
  });

  it('reports expired sessions instead of downloading HTML', async () => {
    fetch.mockResolvedValue({ ok: true, headers: new Headers({ 'content-type': 'text/html' }) });
    render(<AdministrativeExport role="ADMIN" />);
    fireEvent.click(screen.getByText('Full Administrative Export'));
    fireEvent.change(screen.getByLabelText('Export start date'), { target: { value: '2026-01-01' } });
    fireEvent.change(screen.getByLabelText('Export end date'), { target: { value: '2026-01-31' } });
    fireEvent.click(screen.getByRole('button', { name: 'Download administrative XLSX' }));
    await waitFor(() => expect(screen.getByRole('status').textContent).toContain('Sign in and try again.'));
  });
});
