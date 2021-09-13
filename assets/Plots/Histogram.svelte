<div class="cc-plot"
    on:mousemove={mousemove}
    on:mouseleave={() => (infoText = '')}>
    <span style="left: {paddingLeft + 5}px;">{infoText}</span>
    <canvas bind:this={canvasElement} width="{width}" height="{height}"></canvas>
</div>

<style>
    .cc-plot {
        position: relative;
    }
    .cc-plot > span {
        position: absolute;
        top: 0px;
    }
</style>

<script>
    import { onMount } from "svelte";
    import { formatNumber } from "../Common/utils.js"

    export let data;
    export let width;
    export let height;
    export let min = null;
    export let max = null;
    export let label = formatNumber;

    const fontSize = 12;
    const fontFamily = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"';
    const paddingLeft = 35,
        paddingRight = 20,
        paddingTop = 20,
        paddingBottom = 20;

    let ctx;
    let canvasElement;

    const [ maxCount, maxValue ] = data.reduce(([maxCount, maxValue], point) =>
        [ Math.max(maxCount, point.count), Math.max(maxValue, point.value) ], [0, 0]);

    function getStepSize(valueRange, pixelRange, minSpace) {
        const proposition = valueRange / (pixelRange / minSpace);
        const getStepSize = n => Math.pow(10, Math.floor(n / 3)) *
            (n < 0 ? [1., 5., 2.][-n % 3] : [1., 2., 5.][n % 3]);

        let n = 0;
        let stepsize = getStepSize(n);
        while (true) {
            let bigger = getStepSize(n + 1);
            if (proposition > bigger) {
                n += 1;
                stepsize = bigger;
            } else {
                return stepsize;
            }
        }
    }

    let infoText = '';
    function mousemove(event) {
        let rect = event.target.getBoundingClientRect();
        let x = event.clientX - rect.left;
        if (x < paddingLeft || x > width - paddingRight) {
            infoText = '';
            return;
        }

        const w = width - paddingLeft - paddingRight;
        const barWidth = Math.round(w / (maxValue + 1));
        x = Math.floor((x - paddingLeft) / (w - barWidth) * maxValue);
        let point = data.find(point => point.value == x);

        if (point)
            infoText = `count: ${point.count} (value: ${label(x)})`;
        else
            infoText = '';
    }

    function render() {
        const h = height - paddingTop - paddingBottom;
        const w = width - paddingLeft - paddingRight;
        const barWidth = Math.ceil(w / (maxValue + 1));

        const getCanvasX = (value) => (value / maxValue) * (w - barWidth) + paddingLeft + (barWidth / 2.);
        const getCanvasY = (count) => (h - (count / maxCount) * h) + paddingTop;

        // X Axis
        ctx.font = `${fontSize}px ${fontFamily}`;
        ctx.fillStyle = 'black';
        ctx.textAlign = 'center';
        if (min != null && max != null) {
            const stepsizeX = getStepSize(max - min, w, 75);
            let startX = 0;
            while (startX < min)
                startX += stepsizeX;

            for (let x = startX; x < max; x += stepsizeX) {
                let px = ((x - min) / (max - min)) * (w - barWidth) + paddingLeft + (barWidth / 2.);
                ctx.fillText(`${formatNumber(x)}`, px, height - paddingBottom + 15);
            }
        } else {
            const stepsizeX = getStepSize(maxValue, w, 120);
            for (let x = 0; x <= maxValue; x += stepsizeX) {
                ctx.fillText(label(x), getCanvasX(x), height - paddingBottom + 15);
            }
        }

        // Y Axis
        ctx.fillStyle = 'black';
        ctx.strokeStyle = '#bbbbbb';
        ctx.textAlign = 'right';
        ctx.beginPath();
        const stepsizeY = getStepSize(maxCount, h, 50);
        for (let y = stepsizeY; y <= maxCount; y += stepsizeY) {
            const py = Math.floor(getCanvasY(y));
            ctx.fillText(`${formatNumber(y)}`, paddingLeft - 5, py);
            ctx.moveTo(paddingLeft, py);
            ctx.lineTo(width, py);
        }
        ctx.stroke();

        // Draw bars
        ctx.fillStyle = '#0066cc';
        for (let p of data) {
            ctx.fillRect(
                getCanvasX(p.value) - (barWidth / 2.),
                getCanvasY(p.count),
                barWidth,
                (p.count / maxCount) * h);
        }

        // Fat lines left and below plotting area
        ctx.strokeStyle = 'black';
        ctx.beginPath();
        ctx.moveTo(0, height - paddingBottom);
        ctx.lineTo(width, height - paddingBottom);
        ctx.moveTo(paddingLeft, 0);
        ctx.lineTo(paddingLeft, height- paddingBottom);
        ctx.stroke();
    }

    let mounted = false;
    onMount(() => {
        mounted = true;
        canvasElement.width = width;
        canvasElement.height = height;
        ctx = canvasElement.getContext('2d');
        render();
    });

    let timeoutId = null;
    function sizeChanged() {
        if (timeoutId != null)
            clearTimeout(timeoutId);

        timeoutId = setTimeout(() => {
            timeoutId = null;
            if (!canvasElement)
                return;

            canvasElement.width = width;
            canvasElement.height = height;
            ctx = canvasElement.getContext('2d');
            render();
        }, 250);
    }

    $: sizeChanged(width, height);
</script>
