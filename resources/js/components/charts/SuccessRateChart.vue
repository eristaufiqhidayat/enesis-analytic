<template>
    <VChart class="h-[300px] w-full" :option="option" autoresize />
</template>

<script setup>
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { COLORS, axisStyle, useChartTheme } from './echarts';
import { num, thousandsK } from '../../utils/format';

const props = defineProps({
    rows: { type: Array, default: () => [] }, // [{ label, call, ec, success_rate }]
    compactLabels: { type: Boolean, default: false },
});

const dark = useChartTheme();

const option = computed(() => {
    const s = axisStyle(dark.value);
    const fmt = props.compactLabels ? thousandsK : num;
    const label = (color) => ({
        show: true,
        position: 'top',
        fontSize: 10,
        color: s.text,
        formatter: ({ value }) => fmt(value),
    });

    return {
        animationDuration: 400,
        grid: { left: 48, right: 44, top: 44, bottom: 8, containLabel: true },
        legend: { top: 0, itemWidth: 14, itemHeight: 10, textStyle: { color: s.text, fontSize: 14 }, data: ['Call', 'EC', 'Success Rate'] },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            valueFormatter: (v) => (typeof v === 'number' && v <= 100 && !Number.isInteger(v) ? `${v.toFixed(1)}%` : num(v)),
        },
        xAxis: {
            type: 'category',
            data: props.rows.map((r) => r.label),
            axisLabel: { rotate: 45, color: s.text, fontSize: 11, interval: 0 },
            axisLine: { lineStyle: { color: s.muted } },
            axisTick: { show: false },
        },
        yAxis: [
            {
                type: 'value',
                axisLabel: { color: s.muted, fontSize: 11, formatter: (v) => fmt(v) },
                splitLine: { lineStyle: { color: s.grid, type: 'dashed' } },
            },
            {
                type: 'value',
                min: 0,
                max: 100,
                interval: 25,
                axisLabel: { color: s.muted, fontSize: 11, formatter: '{value}%' },
                splitLine: { show: false },
            },
        ],
        series: [
            { name: 'Call', type: 'bar', barGap: '10%', itemStyle: { color: COLORS.call, borderRadius: [2, 2, 0, 0] }, label: label(COLORS.call), data: props.rows.map((r) => r.call) },
            { name: 'EC', type: 'bar', itemStyle: { color: COLORS.ec, borderRadius: [2, 2, 0, 0] }, label: label(COLORS.ec), data: props.rows.map((r) => r.ec) },
            {
                name: 'Success Rate',
                type: 'line',
                yAxisIndex: 1,
                symbol: 'circle',
                symbolSize: 6,
                lineStyle: { color: COLORS.rate, width: 2 },
                itemStyle: { color: dark.value ? '#0f172a' : '#fff', borderColor: COLORS.rate, borderWidth: 1.5 },
                data: props.rows.map((r) => Number(r.success_rate.toFixed(2))),
            },
        ],
    };
});
</script>
