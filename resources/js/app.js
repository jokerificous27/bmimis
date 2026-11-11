import './weigh-in';
import Chart from 'chart.js/auto';

const palette = {
    navy: '#1d3461',
    blue: '#3b6fb6',
    sky: '#7fb0e0',
    green: '#2f8f5b',
    lime: '#7cb342',
    amber: '#d99a1e',
    orange: '#e0742b',
    red: '#c0392b',
    rose: '#b03a5b',
    zinc: '#8a8f98',
};

window.bmiPalette = palette;

function themed(config) {
    const dark = document.documentElement.classList.contains('dark');
    const text = dark ? '#d4d4d8' : '#3f3f46';
    const grid = dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';

    Chart.defaults.color = text;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.borderColor = grid;

    config.options = Object.assign(
        {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } },
        },
        config.options || {},
    );

    return config;
}

/**
 * Alpine component used by <x-chart>. Charts are re-created when Livewire replaces the element
 * (the element is keyed by a hash of its data), so they always reflect live database figures.
 */
window.bmiChart = (config) => ({
    chart: null,
    init() {
        this.chart = new Chart(this.$refs.canvas, themed(structuredClone(config)));
    },
    destroy() {
        this.chart?.destroy();
    },
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('bmiChart', window.bmiChart);
});
