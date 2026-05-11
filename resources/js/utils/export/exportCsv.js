function escapeCsvValue(value) {
    return `"${String(value).replaceAll('"', '""')}"`;
}

export function exportToCsv(rows, columns, fileName) {
    const header = columns.map((col) => escapeCsvValue(col.header)).join(',');
    const body = rows.map((row) =>
        columns.map((col) => {
            const raw = col.accessor(row);
            return escapeCsvValue(raw === null || raw === undefined ? '' : String(raw));
        }).join(',')
    );
    const csv = [header, ...body].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName.endsWith('.csv') ? fileName : `${fileName}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}
