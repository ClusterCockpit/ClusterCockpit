<div class="cc-plot">
    <canvas bind:this={canvasElement} width="600" height="400"></canvas>
</div>

<script context="module">
    const axesColor = '#aaaaaa';
    const fontSize = 12;
    const colors = '#cc9900';
    const paddingLeft = 40,
        paddingRight = 10,
        paddingTop = 10,
        paddingBottom = 50;

    function getGradientR(x) {
        if (x < 0.5) return 0;
        if (x > 0.75) return 255;
        x = (x - 0.5) * 4.0;
        return Math.floor(x * 255.0);
    }

    function getGradientG(x) {
        if (x > 0.25 && x < 0.75) return 255;
        if (x < 0.25) x = x * 4.0;
        else          x = 1.0 - (x - 0.75) * 4.0;
        return Math.floor(x * 255.0);
    }

    function getGradientB(x) {
        if (x < 0.25) return 255;
        if (x > 0.5) return 0;
        x = 1.0 - (x - 0.25) * 4.0;
        return Math.floor(x * 255.0);
    }

    function getRGB(c) {
        return `rgb(${getGradientR(c)}, ${getGradientG(c)}, ${getGradientB(c)})`;
    }

    function getStepSize(valueRange, pixelRange, minSpace) {
        const proposition = valueRange / (pixelRange / minSpace);
        const getStepSize = n => Math.pow(10, Math.floor(n / 3)) *
            (n < 0 ? [1., 5., 2.][-n % 3] : [1., 2., 5.][n % 3]);

        let n = 0;
        let stepsize = getStepSize(n);
        while (true) {
            let smaller = getStepSize(n - 1);
            let bigger = getStepSize(n + 1);

            if (proposition < smaller) {
                n -= 1;
                stepsize = smaller;
            } else if (proposition > bigger) {
                n += 1;
                stepsize = bigger;
            } else {
                return stepsize;
            }
        }
    }

    const power = [1, 1e3, 1e6, 1e9, 1e12];
    const suffix = ['', 'k', 'm', 'g'];
    function formatNumber(x) {
        for (let i = 0; i < suffix.length; i++)
            if (power[i] <= x && x < power[i+1])
                return `${x / power[i]}${suffix[i]}`;

        return Math.abs(x) >= 1000 ? x.toExponential() : x.toString();
    }

    function render(ctx, data, cluster, width, height) {
        const [minX, maxX, minY, maxY] = [0., 32, 0., cluster.flopRateSimd * 1.1];
        const w = width - paddingLeft - paddingRight;
        const h = height - paddingTop - paddingBottom;

        // Helpers:
        const getCanvasX = (x) => {
            x -= minX; x /= (maxX - minX);
            return Math.round((x * w) + paddingLeft);
        };

        const getCanvasY = (y) => {
            y -= minY; y /= (maxY - minY);
            return Math.round((h - y * h) + paddingTop);
        };

        // Axes
        ctx.strokeStyle = axesColor;
        ctx.font = `${fontSize}px sans-serif`;
        ctx.beginPath();
        const stepsizeX = getStepSize(maxX - minX, w, 100);
        for (let x = minX; x <= maxX; x += stepsizeX) {
            let px = getCanvasX(x);
            let text = formatNumber(x);
            let textWidth = ctx.measureText(text).width;
            ctx.fillText(text, px - (textWidth / 2), height - paddingBottom + fontSize + 5);
            ctx.moveTo(px, paddingTop - 5);
            ctx.lineTo(px, height - paddingBottom + 5);
        }
        if (data.xLabel) {
            let textWidth = ctx.measureText(data.xLabel).width;
            ctx.fillText(data.xLabel, (width / 2) - (textWidth / 2), height - 20);
        }
        const stepsizeY = getStepSize(maxY - minY, h, 75);
        ctx.textAlign = 'center';
        for (let y = minY; y <= maxY; y += stepsizeY) {
            let py = getCanvasY(y);
            ctx.moveTo(paddingLeft - 5, py);
            ctx.lineTo(width - paddingRight + 5, py);

            ctx.save();
            ctx.translate(paddingLeft - 10, py);
            ctx.rotate(-Math.PI / 2);
            ctx.fillText(formatNumber(y), 0, 0);
            ctx.restore();
        }
        if (data.yLabel) {
            ctx.save();
            ctx.translate(15, height / 2);
            ctx.rotate(-Math.PI / 2);
            ctx.fillText(data.yLabel, 0, 0);
            ctx.restore();
        }
        ctx.stroke();

        // Draw Data
        for (let i = 0; i < data.x.length; i++) {
            let x = data.x[i], y = data.y[i], c = data.c[i];
            if (x == null || y == null || Number.isNaN(x) || Number.isNaN(y))
                continue;

            const s = 3;
            const px = getCanvasX(x);
            const py = getCanvasY(y);

            ctx.fillStyle = getRGB(c);
            ctx.beginPath();
            ctx.arc(px, py, s, 0, Math.PI * 2, false);
            ctx.fill();
        }

        // Draw roofs
        ctx.strokeStyle = 'black';
        ctx.lineWidth = 2;
        ctx.beginPath();
        {
            const ycut = 0.01 * cluster.memoryBandwidth;
            const scalarKnee = (cluster.flopRateScalar - ycut) / cluster.memoryBandwidth;
            const simdKnee = (cluster.flopRateSimd - ycut) / cluster.memoryBandwidth;

            ctx.moveTo(getCanvasX(scalarKnee), getCanvasY(cluster.flopRateScalar));
            ctx.lineTo(width - paddingRight, getCanvasY(cluster.flopRateScalar));

            ctx.moveTo(getCanvasX(simdKnee), getCanvasY(cluster.flopRateSimd));
            ctx.lineTo(width - paddingRight, getCanvasY(cluster.flopRateSimd));

            ctx.moveTo(getCanvasX(0.01), getCanvasY(ycut));
            ctx.lineTo(getCanvasX(simdKnee), getCanvasY(cluster.flopRateSimd));
        }
        ctx.stroke();

        // The Color Scale
        ctx.fillStyle = 'black';
        ctx.fillText('Time:', 17, height - 5);
        const start = paddingLeft + 5;
        for (let x = start; x < width - paddingRight; x += 15) {
            let c = (x - start) / (width - start - paddingRight);
            ctx.fillStyle = getRGB(c);
            ctx.beginPath();
            ctx.arc(x, height - 10, 5, 0, Math.PI * 2, false);
            ctx.fill();
        }
    }

    function avg(x) {
        let a = 0.;
        for (let value of x)
            a += value;

        return a / x.length;
    }

    function transformData(flopsAny, memBw) {
        const nodes = flopsAny.series.length;
        const timesteps = flopsAny.series[0].data.length;

        /* c will contain values from 0 to 1 representing the time */
        const x = [], y = [], c = [];
        let maxX = Number.NEGATIVE_INFINITY;
        for (let i = 0; i < nodes; i++) {
            const flopsData = flopsAny.series[i].data;
            const memBwData = memBw.series[i].data;
            for (let j = 0; j < timesteps; j++) {
                const f = flopsData[j], m = memBwData[j];
                const intensity = f / m;
                if (Number.isNaN(intensity) || !Number.isFinite(intensity))
                    continue;

                maxX = Math.max(maxX, intensity);
                x.push(intensity);
                y.push(f);
                c.push(j / timesteps);
            }
        }

        return {
            x, y, c, maxX: avg(x) * 2.,
            xLabel: 'Intensity [FLOPS/byte]',
            yLabel: 'Performance [GFLOPS]'
        };
    }
</script>

<script>
    import { onMount, getContext } from 'svelte';

    export let flopsAny
    export let memBw;
    export let cluster;
    let canvasElement;
    let metricConfig = getContext('metric-config');

    onMount(() => {
        const ctx = canvasElement.getContext('2d');

        setTimeout(() => {
            console.time('render-roofline');
            const data = transformData(flopsAny, memBw);
            render(ctx, data, cluster, canvasElement.width, canvasElement.height);
            console.timeEnd('render-roofline');
        }, 0);
    });
</script>
