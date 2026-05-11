import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

export function exportToPdf(rows, columns, fileName, options = {}) {
    const doc = new jsPDF({ orientation: options.orientation ?? 'landscape' });
    const title = options.title?.trim();

    if (title) {
        doc.setFontSize(14);
        doc.text(title, 14, 14);
    }

    autoTable(doc, {
        startY: title ? 20 : 14,
        head: [columns.map((col) => col.header)],
        body: rows.map((row) =>
            columns.map((col) => {
                const raw = col.accessor(row);
                return raw === null || raw === undefined ? '' : String(raw);
            })
        ),
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [11, 83, 132], textColor: [255, 255, 255], fontStyle: 'bold' },
    });

    const fn = fileName.endsWith('.pdf') ? fileName : `${fileName}.pdf`;
    doc.save(fn);
}
