<template>
    <section class="card p-4">
        <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-slate-800 dark:text-slate-200">
            <svg viewBox="0 0 24 24" class="h-4 w-4 text-accent-500" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" /></svg>
            Filter
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-12">
            <div>
                <label class="field-label">Tahun</label>
                <select v-model.number="year" class="field" @change="applyMonth">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>
            <div>
                <label class="field-label">Bulan</label>
                <select v-model.number="month" class="field" @change="applyMonth">
                    <option v-for="(m, i) in MONTHS" :key="m" :value="i">{{ m }}</option>
                </select>
            </div>
            <div>
                <label class="field-label">Tanggal Awal</label>
                <input v-model="model.date_from" type="date" class="field" :max="model.date_to" />
            </div>
            <div>
                <label class="field-label">Tanggal Akhir</label>
                <input v-model="model.date_to" type="date" class="field" :min="model.date_from" />
            </div>
            <div>
                <label class="field-label">Propinsi</label>
                <select v-model="model.province_id" class="field" @change="onProvince">
                    <option value="">Semua</option>
                    <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <label class="field-label">Kota</label>
                <select v-model="model.city_id" class="field" @change="onCity">
                    <option value="">Semua</option>
                    <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <label class="field-label">11 Kota Besar</label>
                <select v-model="model.big_city_id" class="field" @change="onBigCity">
                    <option value="">Semua</option>
                    <option v-for="b in options.big_cities" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>
            <div>
                <label class="field-label">Depo</label>
                <select v-model="model.warehouse_id" class="field" @change="model.koordinator_id = ''">
                    <option value="">Semua</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
            </div>
            <div>
                <label class="field-label">Sales Koordinator (SK)</label>
                <select v-model="model.koordinator_id" class="field">
                    <option value="">Semua</option>
                    <option v-for="k in koordinators" :key="k.id" :value="k.id">{{ k.name }}</option>
                </select>
            </div>
            <div>
                <label class="field-label">Brand</label>
                <select v-model="model.brand_id" class="field" @change="model.product_id = ''">
                    <option value="">Semua</option>
                    <option v-for="b in options.brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>
            <div>
                <label class="field-label">SKU / Produk</label>
                <select v-model="model.product_id" class="field">
                    <option value="">Semua</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" class="h-8 w-full rounded-md border border-slate-300 bg-white text-xs font-medium hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700" @click="$emit('reset')">
                    Reset
                </button>
            </div>
        </div>
        <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-400">
            <span class="font-semibold text-slate-800 dark:text-slate-200">HK Nasional periode:</span>
            <span class="ml-1 font-bold text-accent-600">{{ hkNasional }} hari</span>
            <span class="ml-1">· AVG/Hari dihitung dari hari kerja aktual masing-masing Sales Motoris</span>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { MONTHS, toIsoDate } from '../utils/format';

const model = defineModel({ type: Object, required: true });
const props = defineProps({
    options: { type: Object, required: true },
    hkNasional: { type: Number, default: 0 },
});
defineEmits(['reset']);

const years = computed(() => (props.options.years?.length ? props.options.years : [new Date().getFullYear()]));
const year = ref(new Date(model.value.date_from).getFullYear());
const month = ref(new Date(model.value.date_from).getMonth());

watch(
    () => model.value.date_from,
    (value) => {
        const d = new Date(value);
        if (!Number.isNaN(d.getTime())) {
            year.value = d.getFullYear();
            month.value = d.getMonth();
        }
    },
);

function applyMonth() {
    const first = new Date(year.value, month.value, 1);
    const last = new Date(year.value, month.value + 1, 0);
    const today = new Date();
    model.value.date_from = toIsoDate(first);
    model.value.date_to = toIsoDate(last > today && first <= today ? today : last);
}

const provinces = computed(() => {
    const used = new Set((props.options.warehouses ?? []).map((w) => w.province_id));
    const list = props.options.provinces ?? [];
    return [...list.filter((p) => used.has(p.id)), ...list.filter((p) => !used.has(p.id))];
});
const cities = computed(() => (props.options.cities ?? []).filter((c) => !model.value.province_id || c.province_id === model.value.province_id));
const warehouses = computed(() =>
    (props.options.warehouses ?? []).filter(
        (w) =>
            (!model.value.province_id || w.province_id === model.value.province_id) &&
            (!model.value.city_id || w.city_id === model.value.city_id) &&
            (!model.value.big_city_id || w.big_city_id === model.value.big_city_id),
    ),
);
const koordinators = computed(() => {
    const allowed = new Set(warehouses.value.map((w) => w.id));
    const filtered = !model.value.province_id && !model.value.city_id && !model.value.big_city_id && !model.value.warehouse_id;
    return (props.options.koordinators ?? []).filter((k) =>
        filtered ? true : model.value.warehouse_id ? k.warehouse_id === model.value.warehouse_id : allowed.has(k.warehouse_id),
    );
});
const products = computed(() => (props.options.products ?? []).filter((p) => !model.value.brand_id || p.brand_id === model.value.brand_id));

function onProvince() {
    model.value.city_id = '';
    model.value.warehouse_id = '';
    model.value.koordinator_id = '';
}
function onCity() {
    model.value.warehouse_id = '';
    model.value.koordinator_id = '';
}
function onBigCity() {
    model.value.warehouse_id = '';
    model.value.koordinator_id = '';
}
</script>
