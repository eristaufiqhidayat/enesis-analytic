<template>
    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-3 p-5 pb-3">
            <div class="max-w-4xl">
                <h3 class="text-[15px] font-bold uppercase tracking-tight">Data Table — Sales Motoris</h3>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                    AVG &amp; SR Call/EC dihitung dari <b>Hari Hadir</b> (hari sales tersebut check-in di sistem). <i>Hari Kerja</i> = jumlah hari kerja nasional (HK Nasional)
                    dalam periode filter, ditampilkan sebagai referensi.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs leading-tight text-slate-700 dark:text-slate-300">{{ num(pagination.total) }} Sales<br />Motoris</span>
                <button type="button" class="btn-export !px-4 !py-2" :disabled="exporting" @click="doExport('csv')">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 3v12M7 10l5 5 5-5M5 21h14" /></svg>
                    Export CSV
                </button>
                <button type="button" class="btn-export !bg-emerald-600 !px-4 !py-2 hover:!bg-emerald-700" :disabled="exporting" @click="doExport('xlsx')">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 3v12M7 10l5 5 5-5M5 21h14" /></svg>
                    Export Excel
                </button>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-auto">
            <table class="w-full min-w-[1700px] text-xs">
                <thead class="sticky top-0 z-10 bg-accent-600 text-white">
                    <tr>
                        <th v-for="c in columns" :key="c.label" class="whitespace-nowrap px-3 py-3 font-semibold" :class="c.align === 'left' ? 'text-left' : 'text-center'">{{ c.label }}</th>
                    </tr>
                </thead>
                <tbody :class="{ 'opacity-50': loading }">
                    <tr v-for="(row, i) in rows" :key="row.id_frontliner" class="border-b border-slate-100 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                        <td v-for="c in columns" :key="c.label" class="whitespace-nowrap px-3 py-2 tabular-nums" :class="[c.align === 'left' ? 'text-left' : 'text-center', c.class?.(row)]">
                            <template v-if="c.key === 'no'">{{ (pagination.page - 1) * pagination.limit + i + 1 }}</template>
                            <template v-else-if="c.key === 'status'">
                                <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold" :class="row.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'">
                                    {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </template>
                            <template v-else>{{ c.display(row) }}</template>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td :colspan="columns.length" class="px-3 py-8 text-center text-slate-500">Tidak ada data untuk filter ini.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-xs dark:border-slate-800">
            <span class="text-slate-600 dark:text-slate-400">{{ rangeLabel }}</span>
            <div class="flex items-center gap-2">
                <button type="button" class="rounded-md border border-slate-300 px-2.5 py-1 disabled:opacity-40 dark:border-slate-700" :disabled="pagination.page <= 1" @click="$emit('page', pagination.page - 1)">‹ Sebelumnya</button>
                <span class="font-medium">{{ pagination.page }} / {{ pagination.total_pages || 1 }}</span>
                <button type="button" class="rounded-md border border-slate-300 px-2.5 py-1 disabled:opacity-40 dark:border-slate-700" :disabled="pagination.page >= pagination.total_pages" @click="$emit('page', pagination.page + 1)">Berikutnya ›</button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { num, dec1, dec2, pct, rupiahShort, achClass } from '../utils/format';
import { exportCsv, exportExcel } from '../utils/export';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({ page: 1, limit: 50, total: 0, total_pages: 1 }) },
    loading: { type: Boolean, default: false },
    fetchAll: { type: Function, required: true },
});
defineEmits(['page']);

const blue = () => 'font-semibold text-brand-600 dark:text-brand-100';
const columns = [
    { key: 'no', label: 'No', align: 'left' },
    { label: 'ID', align: 'left', display: (r) => r.id_frontliner, value: (r) => r.id_frontliner },
    { label: 'Sales Motoris', align: 'left', class: () => 'font-semibold', display: (r) => r.frontliner, value: (r) => r.frontliner },
    { key: 'status', label: 'Status', align: 'left', value: (r) => (r.is_active ? 'Aktif' : 'Nonaktif') },
    { label: 'Sales Koordinator (SK)', align: 'left', display: (r) => r.teamleader ?? '-', value: (r) => r.teamleader ?? '' },
    { label: 'Depo', align: 'left', display: (r) => r.project, value: (r) => r.project },
    { label: 'Hari Kerja', class: blue, display: (r) => r.hari_kerja, value: (r) => r.hari_kerja },
    { label: 'Hari Hadir', class: blue, display: (r) => r.hari_hadir, value: (r) => r.hari_hadir },
    { label: 'Target Call/Hari', display: (r) => r.target_call, value: (r) => r.target_call },
    { label: 'Call', display: (r) => num(r.call), value: (r) => r.call },
    { label: 'Avg Call/Hari', display: (r) => dec1(r.avg_call), value: (r) => r.avg_call },
    { label: 'SR Call/Hari', class: (r) => achClass(r.sr_call) + ' font-semibold', display: (r) => pct(r.sr_call), value: (r) => r.sr_call },
    { label: 'Target EC/Hari', display: (r) => r.target_ec, value: (r) => r.target_ec },
    { label: 'EC', display: (r) => num(r.ec), value: (r) => r.ec },
    { label: 'Avg EC/Hari', display: (r) => dec1(r.avg_ec), value: (r) => r.avg_ec },
    { label: 'SR EC/Hari', class: (r) => achClass(r.sr_ec) + ' font-semibold', display: (r) => pct(r.sr_ec), value: (r) => r.sr_ec },
    { label: 'Target IPT', display: (r) => r.target_ipt, value: (r) => r.target_ipt },
    { label: 'Avg IPT/Hari', display: (r) => dec2(r.avg_ipt), value: (r) => r.avg_ipt },
    { label: 'SR IPT', class: (r) => achClass(r.sr_ipt) + ' font-semibold', display: (r) => pct(r.sr_ipt), value: (r) => r.sr_ipt },
    { label: 'Total Sales', class: () => 'font-semibold', display: (r) => rupiahShort(r.total_sales), value: (r) => r.total_sales },
];

const rangeLabel = computed(() => {
    const { page, limit, total } = props.pagination;
    if (!total) return '0 data';
    const start = (page - 1) * limit + 1;
    return `${num(start)}–${num(Math.min(total, page * limit))} dari ${num(total)}`;
});

const exporting = ref(false);
async function doExport(type) {
    exporting.value = true;
    try {
        const all = await props.fetchAll();
        const exportColumns = columns.map((c) => (c.key === 'no' ? { label: 'No', value: (r) => all.indexOf(r) + 1 } : c));
        if (type === 'csv') exportCsv('sales-motoris', exportColumns, all);
        else await exportExcel('sales-motoris', exportColumns, all, 'Sales Motoris');
    } finally {
        exporting.value = false;
    }
}
</script>
