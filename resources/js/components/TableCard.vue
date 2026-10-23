<template>
    <section class="card flex flex-col p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h3 class="text-[15px] font-semibold">{{ title }}</h3>
            <div class="flex gap-1.5">
                <button type="button" class="btn-export" @click="exportCsv(fileName, columns, exportRows)">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 3v12M7 10l5 5 5-5M5 21h14" /></svg>
                    CSV
                </button>
                <button type="button" class="btn-export" @click="exportExcel(fileName, columns, exportRows, title)">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" /><path d="M14 3v6h6M9 13h6M9 17h6" /></svg>
                    Excel
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="kpi-table">
                <thead>
                    <tr>
                        <th v-for="c in columns" :key="c.label">{{ c.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                        <td v-for="c in columns" :key="c.label" :class="[c.class?.(row), c.first ? 'font-medium' : '']">{{ c.display ? c.display(row) : c.value(row) }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td :colspan="columns.length" class="!text-center text-slate-500">Tidak ada data untuk filter ini.</td>
                    </tr>
                </tbody>
                <tfoot v-if="total">
                    <tr>
                        <td v-for="c in columns" :key="c.label">{{ c.display ? c.display(total) : c.value(total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { exportCsv, exportExcel } from '../utils/export';

const props = defineProps({
    title: { type: String, required: true },
    columns: { type: Array, required: true }, // [{ label, value(row), display?(row), class?(row) }]
    rows: { type: Array, default: () => [] },
    total: { type: Object, default: null },
    fileName: { type: String, default: 'export' },
});

const exportRows = computed(() => (props.total ? [...props.rows, props.total] : props.rows));
</script>
