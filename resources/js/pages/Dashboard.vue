<template>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Dashboard Performance</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Ringkasan performa penjualan &amp; aktivitas motoris</p>
            </div>
            <button type="button" class="mt-1 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800" :disabled="loading" @click="load()">
                <svg viewBox="0 0 24 24" class="h-4 w-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6" /></svg>
                Refresh
            </button>
        </div>

        <div class="sticky top-14 z-20">
            <FilterBar v-model="filters" :options="options" :hk-nasional="data?.period?.hk_nasional ?? 0" @reset="resetFilters" />
        </div>

        <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ error }}</p>

        <div class="grid gap-6 lg:grid-cols-2" :class="{ 'opacity-60 transition-opacity': loading }">
            <section class="card p-5">
                <h3 class="mb-2 text-[15px] font-semibold uppercase">Success Rate by Depo</h3>
                <SuccessRateChart :rows="successByDepo" />
            </section>
            <section class="card p-5">
                <h3 class="mb-2 text-[15px] font-semibold uppercase">Total Sales by Depo</h3>
                <SalesBarChart :rows="salesByDepo" />
            </section>
            <section class="card p-5">
                <h3 class="mb-2 text-[15px] font-semibold uppercase">Success Rate — 11 Kota Besar</h3>
                <SuccessRateChart :rows="successByCity" compact-labels />
            </section>
            <section class="card p-5">
                <h3 class="mb-2 text-[15px] font-semibold uppercase">Total Sales — 11 Kota Besar</h3>
                <SalesBarChart :rows="salesByCity" />
            </section>

            <TableCard title="Kuota Motoris" file-name="kuota-motoris" :columns="quotaColumns" :rows="data?.motoris_quota ?? []" :total="quotaTotal" />
            <TableCard title="Call" file-name="call-per-depo" :columns="kpiColumns(dec1, 'Call')" :rows="data?.call_by_depo ?? []" :total="kpiTotal(data?.call_total)" />
            <TableCard title="EC" file-name="ec-per-depo" :columns="kpiColumns(dec1, 'EC')" :rows="data?.ec_by_depo ?? []" :total="kpiTotal(data?.ec_total)" />
            <TableCard title="IPT" file-name="ipt-per-depo" :columns="kpiColumns(dec2, 'IPT')" :rows="data?.ipt_by_depo ?? []" :total="kpiTotal(data?.ipt_total)" />
            <TableCard title="Omset" file-name="omset-per-depo" :columns="kpiColumns(rupiahShort, 'Omset')" :rows="data?.omset_by_depo ?? []" :total="kpiTotal(data?.omset_total)" />
            <TableCard title="SKU Perfromance" file-name="sku-performance" :columns="skuColumns" :rows="skuRows" :total="skuTotal" />
        </div>

        <MotorisTable :rows="data?.frontliner_table ?? []" :pagination="pagination" :loading="loading" :fetch-all="fetchAllMotoris" @page="goToPage" />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import api, { cleanParams } from '../api';
import FilterBar from '../components/FilterBar.vue';
import TableCard from '../components/TableCard.vue';
import MotorisTable from '../components/MotorisTable.vue';
import SuccessRateChart from '../components/charts/SuccessRateChart.vue';
import SalesBarChart from '../components/charts/SalesBarChart.vue';
import { achClass, dec1, dec2, num, pct, qty, rupiahShort, toIsoDate } from '../utils/format';

function defaultFilters() {
    const today = new Date();
    return {
        date_from: toIsoDate(new Date(today.getFullYear(), today.getMonth(), 1)),
        date_to: toIsoDate(today),
        province_id: '',
        city_id: '',
        big_city_id: '',
        warehouse_id: '',
        koordinator_id: '',
        brand_id: '',
        product_id: '',
    };
}

const filters = ref(defaultFilters());
const options = ref({});
const data = ref(null);
const loading = ref(false);
const error = ref('');
const page = ref(1);
const pagination = reactive({ page: 1, limit: 50, total: 0, total_pages: 1 });

let requestId = 0;
async function load(targetPage = page.value) {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    try {
        const { data: res } = await api.get('/dashboard', { params: cleanParams({ ...filters.value, page: targetPage, limit: 50 }) });
        if (id !== requestId) return;
        data.value = res.data;
        Object.assign(pagination, res.data.pagination);
        page.value = res.data.pagination.page;
    } catch (e) {
        if (id === requestId && e.response?.status !== 401) {
            error.value = e.response?.data?.message ?? 'Gagal memuat data dashboard.';
        }
    } finally {
        if (id === requestId) loading.value = false;
    }
}

async function fetchAllMotoris() {
    const { data: res } = await api.get('/dashboard/motoris', { params: cleanParams(filters.value) });
    return res.data.rows;
}

function goToPage(p) {
    load(p);
}

function resetFilters() {
    filters.value = defaultFilters();
}

let timer;
watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => load(1), 300);
    },
    { deep: true },
);

onMounted(() => {
    load(1);
    api.get('/dashboard/filters')
        .then(({ data: f }) => (options.value = f.data ?? {}))
        .catch(() => {});
});

// ---- grafik
const successByDepo = computed(() =>
    (data.value?.by_region ?? []).map((r) => ({ label: r.region, call: r.call, ec: r.ec, success_rate: r.success_rate })),
);
const salesByDepo = computed(() => (data.value?.by_region ?? []).map((r) => ({ label: r.region, value: r.total_sales })));
const successByCity = computed(() =>
    (data.value?.by_big_city ?? []).map((r) => ({ label: r.name, call: r.call, ec: r.ec, success_rate: r.success_rate })),
);
const salesByCity = computed(() => (data.value?.by_big_city ?? []).map((r) => ({ label: r.name, value: r.total_sales })));

// ---- tabel
const quotaColumns = [
    { label: 'Depo', first: true, value: (r) => r.depo },
    { label: 'Kuota', value: (r) => r.kuota, display: (r) => num(r.kuota) },
    { label: 'Actual', value: (r) => r.actual, display: (r) => num(r.actual) },
    { label: 'Ach %', value: (r) => Number(r.ach_pct.toFixed(1)), display: (r) => pct(r.ach_pct), class: (r) => (r.is_total ? '' : achClass(r.ach_pct)) },
];
const quotaTotal = computed(() => {
    const rows = data.value?.motoris_quota ?? [];
    if (!rows.length) return null;
    const kuota = rows.reduce((s, r) => s + r.kuota, 0);
    const actual = rows.reduce((s, r) => s + r.actual, 0);
    return { depo: 'TOTAL', kuota, actual, ach_pct: kuota ? (actual / kuota) * 100 : 0, is_total: true };
});

function kpiColumns(fmt) {
    return [
        { label: 'Depo', first: true, value: (r) => r.depo },
        { label: 'Target', value: (r) => r.target, display: (r) => (fmt === rupiahShort ? rupiahShort(r.target) : r.target) },
        { label: 'Actual', value: (r) => Number(r.actual.toFixed(2)), display: (r) => fmt(r.actual) },
        { label: 'Ach %', value: (r) => Number(r.ach_pct.toFixed(1)), display: (r) => pct(r.ach_pct), class: (r) => (r.is_total ? '' : achClass(r.ach_pct)) },
    ];
}
function kpiTotal(total) {
    return total ? { depo: 'RATA-RATA', ...total, is_total: true } : null;
}

const skuColumns = [
    { label: 'Produk', first: true, value: (r) => r.produk },
    { label: 'Qty Terjual', value: (r) => r.qty, display: (r) => qty(r.qty) },
    { label: 'EB (Distribusi)', value: (r) => r.eb, display: (r) => num(r.eb) },
    { label: 'Omset', value: (r) => r.omset, display: (r) => rupiahShort(r.omset) },
];
const skuRows = computed(() => (data.value?.sku_performance ?? []).filter((r) => !r.is_total));
const skuTotal = computed(() => (data.value?.sku_performance ?? []).find((r) => r.is_total) ?? null);
</script>
