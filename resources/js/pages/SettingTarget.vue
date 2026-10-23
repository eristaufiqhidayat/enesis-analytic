<template>
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Setting Target</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Target KPI harian motoris yang dipakai di Dashboard Performance.</p>
        </div>

        <form class="card grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="save">
            <div>
                <label class="field-label">Berlaku mulai</label>
                <input v-model="form.effective_from" type="date" class="field h-9 text-sm" required />
            </div>
            <div v-for="f in fields" :key="f.key">
                <label class="field-label">{{ f.label }}</label>
                <input v-model.number="form[f.key]" type="number" min="0" :step="f.step ?? 1" class="field h-9 text-sm" required />
            </div>
            <div class="flex items-center gap-3 sm:col-span-2">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60" :disabled="saving">Simpan Target</button>
                <span v-if="message" class="text-sm text-emerald-600">{{ message }}</span>
            </div>
        </form>

        <section class="card overflow-x-auto p-5">
            <h3 class="mb-3 text-[15px] font-semibold">Riwayat Target</h3>
            <table class="kpi-table">
                <thead>
                    <tr>
                        <th>Berlaku</th>
                        <th v-for="f in fields" :key="f.key">{{ f.short }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in history" :key="t.id">
                        <td>{{ t.effective_from }}</td>
                        <td v-for="f in fields" :key="f.key">{{ num(t[f.key]) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';
import { num, toIsoDate } from '../utils/format';

const fields = [
    { key: 'target_call_per_day', label: 'Target Call / hari', short: 'Call' },
    { key: 'target_ec_per_day', label: 'Target EC / hari', short: 'EC' },
    { key: 'target_sku', label: 'Target IPT (SKU per EC)', short: 'IPT', step: 0.01 },
    { key: 'target_omset_per_motoris', label: 'Target Omset / hari (Rp)', short: 'Omset' },
    { key: 'target_point_per_day', label: 'Target Point / hari', short: 'Point' },
    { key: 'target_min_point_insentif', label: 'Minimal Point Insentif', short: 'Min Point' },
    { key: 'target_value_per_day', label: 'Target Value / hari (Rp)', short: 'Value' },
    { key: 'target_qty_per_day', label: 'Target Qty / hari', short: 'Qty' },
];

const form = reactive({ effective_from: toIsoDate(new Date()) });
const history = ref([]);
const saving = ref(false);
const message = ref('');

async function refresh() {
    const { data } = await api.get('/targets');
    history.value = data.data;
    const latest = data.data[0];
    if (latest) fields.forEach((f) => (form[f.key] = Number(latest[f.key])));
}

async function save() {
    saving.value = true;
    message.value = '';
    try {
        await api.post('/targets', form);
        message.value = 'Target tersimpan.';
        await refresh();
    } finally {
        saving.value = false;
    }
}

onMounted(refresh);
</script>
