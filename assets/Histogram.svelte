<div class="cc-plot">
    <canvas bind:this={canvasElement} width="{width}" height="{height}"></canvas>
</div>

<script>
    import { onMount, onDestroy } from "svelte";

    export let data;
    export let width;
    export let height;

    const paddingLeft = 20,
        paddingRight = 40,
        paddingTop = 0,
        paddingBottom = 20;

    let ctx;
    let canvasElement;

    const [ maxCount, maxValue ] = data.reduce(([maxCount, maxValue], point) =>
        [Math.max(maxCount, point.count), Math.max(maxValue, point.value)], [0, 0]);

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

    function render() {
        const h = height - paddingTop - paddingBottom;
        const w = width - paddingLeft - paddingRight;
        const barWidth = Math.round(w / data.length);

        console.log(barWidth, h, w, height, width)

        const getCanvasX = (value) => Math.floor((value / maxValue) * w + paddingLeft + (barWidth / 2.));
        const getCanvasY = (count) => Math.floor((h - (count / maxCount) * h) + paddingTop);

        ctx.fillStyle = '#0066cc';
        for (let p of data) {
            ctx.fillRect(
                getCanvasX(p.value) - (barWidth / 2.) + 2,
                getCanvasY(p.count),
                barWidth - 4,
                Math.floor((p.count / maxCount) * h));
        }

        ctx.beginPath();
        ctx.moveTo(0, height - paddingBottom);
        ctx.lineTo(width, height - paddingBottom);
        ctx.moveTo(paddingLeft, 0);
        ctx.lineTo(paddingLeft, height- paddingBottom);
        ctx.stroke();

        ctx.fillStyle = 'black';
        ctx.textAlign = 'center';
        const stepsizeX = getStepSize(maxValue, w, 100);
        for (let x = 0; x < maxValue; x += stepsizeX) {
            ctx.fillText(`${x}`, getCanvasX(x), height - paddingBottom + 15);
        }

        ctx.strokeStyle = `#bbb`;
        ctx.textAlign = 'right';
        ctx.beginPath();
        const stepsizeY = getStepSize(maxCount, h, 100);
        for (let y = stepsizeY; y < maxCount; y += stepsizeY) {
            const py = getCanvasY(y);
            ctx.fillText(`${y}`, paddingLeft - 5, py);
            ctx.moveTo(paddingLeft, py);
            ctx.lineTo(width, py);
        }
        ctx.stroke();
    }

    onMount(() => {
        canvasElement.width = width;
        canvasElement.height = height;
        ctx = canvasElement.getContext('2d');
        render();
    });
</script>
