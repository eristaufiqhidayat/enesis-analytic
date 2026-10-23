const idNumber = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const idInteger = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

/** 1234567 → "1.234.567" */
export function num(value) {
    return idInteger.format(Number(value) || 0);
}

/** Qty dengan desimal bila ada: 16489.02 → "16.489,02" */
export function qty(value) {
    return idNumber.format(Number(value) || 0);
}

/** Satu desimal (titik) seperti dashboard sumber: 40.749 → "40.7" */
export function dec1(value) {
    return (Number(value) || 0).toFixed(1);
}

export function dec2(value) {
    return (Number(value) || 0).toFixed(2);
}

export function pct(value) {
    return `${(Number(value) || 0).toFixed(1)}%`;
}

/** Rupiah ringkas: 805995500 → "806.0 jt", 1.2e9 → "1.2 M", 750000 → "750.0 rb" */
export function rupiahShort(value, sep = ' ') {
    const v = Number(value) || 0;
    const abs = Math.abs(v);
    if (abs >= 1e9) return `${(v / 1e9).toFixed(1)}${sep}M`;
    if (abs >= 1e6) return `${(v / 1e6).toFixed(1)}${sep}jt`;
    if (abs >= 1e3) return `${(v / 1e3).toFixed(1)}${sep}rb`;
    return `${v}`;
}

/** 53250 → "53k" */
export function thousandsK(value) {
    const v = Number(value) || 0;
    return Math.abs(v) >= 1000 ? `${Math.round(v / 1000)}k` : `${v}`;
}

export function achClass(value) {
    return Number(value) >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400';
}

export function toIsoDate(date) {
    const d = new Date(date);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
