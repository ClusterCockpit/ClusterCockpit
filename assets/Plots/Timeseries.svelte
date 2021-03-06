<div bind:this={plotWrapper} class="cc-plot">
</div>

<style>
    .cc-plot {
        border-radius: 5px;
    }
</style>

<script>
    import { onMount, onDestroy, getContext } from "svelte";
    import uPlot from "uplot";

    const clusterCockpitConfig = getContext('cc-config');

    const resizeSleepTime = 250;
    const peakLineColor = '#000000';
    const lineWidth = clusterCockpitConfig.plot_general_lineWidth / window.devicePixelRatio;
    const lineColors = clusterCockpitConfig.plot_general_colorscheme;
    const backgroundColors = clusterCockpitConfig.plot_general_colorBackground === true
        ? { normal:  'rgba(255, 255, 255, 1.0)', caution: 'rgba(255, 128, 0, 0.3)', alert: 'rgba(255, 0, 0, 0.3)' }
        : null;

    function getTotalAvg(data) {
        let avg = 0;
        for (let series of data.series)
            avg += series.statistics.avg;

        return avg / data.series.length;
    }

    function getBackgroundColor(data, metricConfig) {
        if (!metricConfig || !metricConfig.alert || !metricConfig.caution)
            return backgroundColors.normal;

        let cond = metricConfig.alert < metricConfig.caution
            ? (a, b) => a <= b
            : (a, b) => a >= b;

        let avg = getTotalAvg(data);
        if (Number.isNaN(avg))
            return backgroundColors.normal;

        if (cond(avg, metricConfig.alert))
            return backgroundColors.alert;

        if (cond(avg, metricConfig.caution))
            return backgroundColors.caution;

        return backgroundColors.normal;
    }

    function getLineColor(i, n) {
        if (n >= lineColors.length)
            return lineColors[i % lineColors.length];
        else
            return lineColors[Math.floor((i / n) * lineColors.length)];
    }

    function formatTime(val) {
        let h = Math.floor(val / 3600);
        let m = Math.floor((val % 3600) / 60);
        if (h == 0)
            return `${m}m`;
        else if (m == 0)
            return `${h}h`
        else
            return `${h}:${m}h`;
    }

    function getTimeIncrs(timestep, maxX) {
        let incrs = [];
        for (let t = 60; t < maxX; t *= 10)
            incrs.push(t, t * 2, t * 3, t * 5);

        return incrs;
    }

    export let metric;
    export let clusterId;
    export let data;
    export let width;
    export let height;

    const metricConfig = getContext('metric-config')[clusterId]
        && getContext('metric-config')[clusterId][metric];

    let plotWrapper;
    let uplot = null;
    let timeoutId = null;
    let prevWidth = null, prevHeight = null;

    const longestSeries = data.series.reduce(
        (n, series) => Math.max(n, series.data.length), 0);

    const maxX = longestSeries * data.timestep;
    const plotData = [new Array(longestSeries)];
    const plotSeries = [{}];

    for (let i = 0; i < longestSeries; i++)
        plotData[0][i] = i * data.timestep;

    for (let i = 0; i < data.series.length; i++) {
        plotData.push(data.series[i].data);
        plotSeries.push({
            scale: 'y',
            width: lineWidth,
            stroke: getLineColor(i, data.series.length)
        });
    }

    const opts = {
        width,
        height,
        series: plotSeries,
        axes: [
            {
                space: 35,
                incrs: getTimeIncrs(data.timestep, maxX),
                values: (u, vals) =>
                    vals.map(v =>
                        formatTime(v, maxX))
            },
            {
                scale: 'y',
                grid: { show: true },
                labelFont: 'sans-serif'
            }
        ],
        padding: [0, 10, -20, -10],
        hooks: {},
        scales: { x: { time: false }, y: {} },
        cursor: { show: false },
        legend: { show: false, live: false }
    };

    if (metricConfig && metricConfig.peak) {
        opts.scales.y.range = [0., metricConfig.peak * 1.1];

        opts.hooks.draw = [u => {
            let x0 = u.valToPos(0, 'x', true);
            let x1 = u.valToPos(maxX, 'x', true);
            let y = u.valToPos(metricConfig.peak, 'y', true);

            u.ctx.lineWidth = lineWidth;
            u.ctx.strokeStyle = peakLineColor;
            u.ctx.setLineDash([5, 5]);
            u.ctx.beginPath();
            u.ctx.moveTo(x0, y);
            u.ctx.lineTo(x1, y);
            u.ctx.stroke();
        }];
    }

    function render() {
        if (!width || Number.isNaN(width) || width < 0)
            return;

        /* Prevent unnecessary rerenders */
        if (prevWidth != null && Math.abs(prevWidth - width) < 10)
            return;

        prevWidth = width;
        prevHeight = height;

        if (!uplot) {
            opts.width = width;
            opts.height = height;
            uplot = new uPlot(opts, plotData, plotWrapper);
        } else {
            uplot.setSize({ width, height });
        }
    }

    let mounted = false;
    onMount(() => {
        if (backgroundColors != null) {
            let bg = getBackgroundColor(data, metricConfig);
            plotWrapper.style.backgroundColor = bg;
        }

        render();
        mounted = true;
    });

    onDestroy(() => {
        if (uplot)
            uplot.destroy();

        if (timeoutId != null)
            clearTimeout(timeoutId);
    });

    function onSizeChange() {
        if (!mounted)
            return;

        if (timeoutId != null)
            clearTimeout(timeoutId);

        timeoutId = setTimeout(() => {
            timeoutId = null;
            render();
        }, resizeSleepTime);
    }

    $: onSizeChange(width, height);

</script>
