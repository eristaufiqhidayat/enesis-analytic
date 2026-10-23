import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { BarChart, LineChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import { ref, onBeforeUnmount } from 'vue';

use([CanvasRenderer, BarChart, LineChart, GridComponent, LegendComponent, TooltipComponent]);

export const COLORS = {
    call: '#5b9cf6',
    ec: '#34d399',
    rate: '#f5a524',
    sales: '#14b8a6',
};

/** Warna teks/garis yang mengikuti mode terang/gelap. */
export function useChartTheme() {
    const read = () => document.documentElement.classList.contains('dark');
    const dark = ref(read());
    const update = () => (dark.value = read());
    window.addEventListener('themechange', update);
    onBeforeUnmount(() => window.removeEventListener('themechange', update));
    return dark;
}

export function axisStyle(dark) {
    return {
        text: dark ? '#cbd5e1' : '#1e293b',
        muted: dark ? '#94a3b8' : '#475569',
        grid: dark ? 'rgba(148,163,184,0.18)' : 'rgba(148,163,184,0.35)',
    };
}
