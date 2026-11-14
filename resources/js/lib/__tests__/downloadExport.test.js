import { afterEach, describe, expect, it, vi } from 'vitest';
import { downloadExport } from '../downloadExport';

const headers = { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': 'attachment; filename="cases-export-20261114-082840.xlsx"' };

describe('downloadExport', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('does not save HTML login page redirected from export URL as XLSX', async () => {
    vi.stubGlobal('route', () => '/login');
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<!DOCTYPE html><html></html>', {
      headers: { 'Content-Type': 'text/html' },
    })));
    const save = vi.spyOn(URL, 'createObjectURL');
    await expect(downloadExport('/cases/export-excel')).rejects.toThrow('Export failed');
    expect(save).not.toHaveBeenCalled();
    save.mockRestore();
  });

  it('explains expired session on JSON 401 and redirected login response', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{"message":"Unauthenticated."}', { status: 401, headers: { 'Content-Type': 'application/json' } })));
    await expect(downloadExport('/cases/export-excel')).rejects.toThrow('Session expired. Sign in again');
    vi.stubGlobal('route', () => '/login');
    const response = new Response('<!DOCTYPE html>', { headers: { 'Content-Type': 'text/html' } });
    Object.defineProperties(response, { redirected: { value: true }, url: { value: 'https://example.test/login' } });
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response));
    await expect(downloadExport('/cases/export-excel')).rejects.toThrow('Session expired. Sign in again');
  });

  it('rejects HTML even if a proxy labels it XLSX and preserves real workbook download', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(new Response('<!DOCTYPE html>', { headers })));
    await expect(downloadExport('/cases/export-excel')).rejects.toThrow('Export failed');
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    const create = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:workbook');
    const revoke = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(new Uint8Array([0x50, 0x4b, 0x03, 0x04, 0, 1]), { headers })));
    await downloadExport('/cases/export-excel');
    expect(click).toHaveBeenCalledOnce();
    expect(create).toHaveBeenCalledOnce();
    expect(document.querySelector('a[download]')).toBeNull();
    click.mockRestore();
    create.mockRestore();
    revoke.mockRestore();
  });
});
