<template>
    <!-- Tinggi dikunci di pembungkus: tanpa ini autoresize ECharts bisa terus membesar. -->
    <div class="relative w-full overflow-hidden" :style="{ height: `${height}px` }">
        <VChart :option="option" autoresize style="position: absolute; inset: 0; width: 100%; height: 100%" />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { COLORS, axisStyle, useChartTheme } from './echarts';
import { rupiahShort } from '../../utils/format';

const props = defineProps({
    height: { type: Number, default: 300 },
    rows: { type: Array, default: () => [] }, // [{ label, value }]
});

const dark = useChartTheme();

const option = computed(() => {
    const s = axisStyle(dark.value);
    return {
        animationDuration: 400,
        grid: { left: 40, right: 16, top: 36, bottom: 8, containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => `Rp ${new Intl.NumberFormat('id-ID').format(v)}` },
        xAxis: {
            type: 'category',
            data: props.rows.map((r) => r.label),
            axisLabel: { rotate: 45, color: s.text, fontSize: 11, interval: 0 },
            axisLine: { lineStyle: { color: s.muted } },
            axisTick: { show: false },
        },
        yAxis: {
            type: 'value',
            axisLabel: { color: s.muted, fontSize: 11, formatter: (v) => rupiahShort(v) },
            splitLine: { lineStyle: { color: s.grid, type: 'dashed' } },
        },
        series: [
            {
                name: 'Total Sales',
                type: 'bar',
                barMaxWidth: 34,
                itemStyle: { color: COLORS.sales, borderRadius: [3, 3, 0, 0] },
                label: {
                    show: true,
                    position: 'top',
                    fontSize: 10,
                    lineHeight: 11,
                    color: s.text,
                    formatter: ({ value }) => rupiahShort(value, '\n'),
                },
                data: props.rows.map((r) => r.value),
            },
        ],
    };
});
</script>
