<div class="cc-plot">
    <canvas bind:this={canvasElement} width="{width}" height="{height}"></canvas>
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

    const power = [1, 1e3, 1e6, 1e9, 1e12];
    const suffix = ['', 'k', 'm', 'g'];
    function formatNumber(x) {
        for (let i = 0; i < suffix.length; i++)
            if (power[i] <= x && x < power[i+1])
                return `${x / power[i]}${suffix[i]}`;

        return Math.abs(x) >= 1000 ? x.toExponential() : x.toString();
    }

    function axisStepFactor(i) {
        if (i % 3 == 0)
            return 2;
        else if (i % 3 == 1)
            return 2.5;
        else
            return 2;
    }

    function render(ctx, data, cluster, width, height) {
        const [minX, maxX, minY, maxY] = [0.01, data.maxX, 1., cluster.flopRateSimd];
        const w = width - paddingLeft - paddingRight;
        const h = height - paddingTop - paddingBottom;

        // Helpers:
        const [log10minX, log10maxX, log10minY, log10maxY] =
            [Math.log10(minX), Math.log10(maxX), Math.log10(minY), Math.log10(maxY)];

        /* Value -> Pixel-Coordinate */
        const getCanvasX = (x) => {
            x = Math.log10(x);
            x -= log10minX; x /= (log10maxX - log10minX);
            return Math.round((x * w) + paddingLeft);
        };
        const getCanvasY = (y) => {
            y = Math.log10(y);
            y -= log10minY; y /= (log10maxY - log10minY);
            return Math.round((h - y * h) + paddingTop);
        };

        // Axes
        ctx.strokeStyle = axesColor;
        ctx.font = `${fontSize}px sans-serif`;
        ctx.beginPath();
        for (let x = minX, i = 0; x <= maxX; i++) {
            let px = getCanvasX(x);
            let text = formatNumber(x);
            let textWidth = ctx.measureText(text).width;
            ctx.fillText(text, px - (textWidth / 2), height - paddingBottom + fontSize + 5);
            ctx.moveTo(px, paddingTop - 5);
            ctx.lineTo(px, height - paddingBottom + 5);

            x *= axisStepFactor(i);
        }
        if (data.xLabel) {
            let textWidth = ctx.measureText(data.xLabel).width;
            ctx.fillText(data.xLabel, (width / 2) - (textWidth / 2), height - 20);
        }

        ctx.textAlign = 'center';
        for (let y = minY, i = 0; y <= maxY; i++) {
            let py = getCanvasY(y);
            ctx.moveTo(paddingLeft - 5, py);
            ctx.lineTo(width - paddingRight + 5, py);

            ctx.save();
            ctx.translate(paddingLeft - 10, py);
            ctx.rotate(-Math.PI / 2);
            ctx.fillText(formatNumber(y), 0, 0);
            ctx.restore();

            y *= axisStepFactor(i);
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
            const scalarKneeX = getCanvasX(scalarKnee);
            const simdKneeX = getCanvasX(simdKnee);
            const flopRateScalarY = getCanvasY(cluster.flopRateScalar);
            const flopRateSimdY = getCanvasY(cluster.flopRateSimd);

            if (scalarKneeX < width - paddingRight) {
                ctx.moveTo(scalarKneeX, flopRateScalarY);
                ctx.lineTo(width - paddingRight, flopRateScalarY);
            }

            if (simdKneeX < width - paddingRight) {
                ctx.moveTo(simdKneeX, flopRateSimdY);
                ctx.lineTo(width - paddingRight, flopRateSimdY);
            }

            ctx.moveTo(getCanvasX(0.01), getCanvasY(ycut));
            ctx.lineTo(getCanvasX(simdKnee), flopRateSimdY);
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
            x, y, c, maxX,
            xLabel: 'Intensity [FLOPS/byte]',
            yLabel: 'Performance [GFLOPS]'
        };
    }
</script>

<script>
    import { onMount } from 'svelte';

    export let flopsAny
    export let memBw;
    export let cluster;
    export let width;
    export let height;

    let ctx;
    let canvasElement;
    let mounted = false;
    const data = transformData(flopsAny, memBw);

    onMount(() => {
        canvasElement.width = width;
        canvasElement.height = height;
        ctx = canvasElement.getContext('2d');
        mounted = true;

        render(ctx, data, cluster, width, height);
    });

    let timeoutId = null;
    function sizeChanged() {
        if (!mounted)
            return;

        if (timeoutId != null)
            clearTimeout(timeoutId);

        timeoutId = setTimeout(() => {
            timeoutId = null;

            canvasElement.width = width;
            canvasElement.height = height;
            ctx = canvasElement.getContext('2d');
            render(ctx, data, cluster, width, height);
        }, 250);
    }

    $: sizeChanged(width, height);


</script>
