const XLSX_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

export async function downloadExport(url) {
  const response = await fetch(url, { headers: { Accept: 'application/json' } });
  if (response.status === 401 || (response.redirected && new URL(response.url).pathname === new URL(route('login'), location.href).pathname)) {
    throw new Error('Session expired. Sign in again before exporting.');
  }
  if (!response.ok || response.headers.get('Content-Type')?.split(';')[0].trim() !== XLSX_TYPE) {
    throw new Error('Export failed. Please try again.');
  }

  const blob = await response.blob();
  const signature = new Uint8Array(await blob.slice(0, 4).arrayBuffer());
  if (signature[0] !== 0x50 || signature[1] !== 0x4b || signature[2] !== 0x03 || signature[3] !== 0x04) {
    throw new Error('Export failed. Please try again.');
  }

  const filename = response.headers.get('Content-Disposition')?.match(/filename="([a-zA-Z0-9_.-]+\.xlsx)"/i)?.[1];
  if (!filename) throw new Error('Export failed. Please try again.');
  const objectUrl = URL.createObjectURL(blob);
  try {
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
  } finally {
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000);
  }
}
