/**
 * Ekspor tabel ke CSV / Excel di sisi browser.
 * columns: [{ label, value: (row) => any, type?: 'number' | 'string' }]
 */
function download(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function csvCell(value) {
    const text = value === null || value === undefined ? '' : String(value);
    return /[";\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

export function exportCsv(filename, columns, rows) {
    const lines = [columns.map((c) => csvCell(c.label)).join(';')];
    for (const row of rows) {
        lines.push(columns.map((c) => csvCell(c.value(row))).join(';'));
    }
    // BOM agar Excel membaca UTF-8 dengan benar.
    download(new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8' }), `${filename}.csv`);
}

export async function exportExcel(filename, columns, rows, sheet = 'Data') {
    const { default: writeXlsxFile } = await import('write-excel-file');
    const header = columns.map((c) => ({ value: c.label, fontWeight: 'bold', backgroundColor: '#E6F4F1' }));
    const body = rows.map((row) =>
        columns.map((c) => {
            const value = c.value(row);
            const isNumber = typeof value === 'number' && Number.isFinite(value);
            return { type: isNumber ? Number : String, value: isNumber ? value : value === null || value === undefined ? '' : String(value) };
        }),
    );
    const blob = await writeXlsxFile([header, ...body], {
        sheet,
        columns: columns.map((c) => ({ width: Math.max(10, Math.min(40, c.label.length + 4)) })),
    });
    download(blob, `${filename}.xlsx`);
}
