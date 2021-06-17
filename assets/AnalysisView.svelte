<script>
    import { setContext, getContext } from 'svelte';
    import { initClient, operationStore, query } from '@urql/svelte';
    import { Spinner, Row, Col, Card } from 'sveltestrap';
    import Histogram from './Histogram.svelte';
    import ScatterPlot from './ScatterPlot.svelte';
    import { fetchClusters } from './utils.js';

    const selectedCluster = 'emmy'; // TODO: Make select/configurable
    const metricsInHistograms = ['flops_any', 'cpu_load', 'mem_bw', 'mem_used', 'clock']; // TODO: Make select/configurable
    const scatterPlotPairs = [
        ['flops_any', 'cpu_load'],
        ['flops_any', 'mem_bw'],
        ['flops_any', 'mem_used'],
        ['flops_any', 'clock'],
        ['cpu_load', 'mem_bw'],
        ['cpu_load', 'mem_used'],
        ['cpu_load', 'clock'],
        ['mem_bw', 'mem_used'],
        ['mem_bw', 'clock'],
        ['mem_used', 'clock']
    ]; // TODO: Make select/configurable

    const clusterCockpitConfig = getContext('cc-config');

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`
    });

    const statsQuery = operationStore(`
    query($filter: JobFilterList!, $metrics: [String!]!) {
        jobMetricAverages(filter: $filter, metrics: $metrics)
    }
    `, {
        filter: { list: [] },
        metrics: []
    }, { pause: true });

    query(statsQuery);

    const metricUnits = {};
    const metricConfig = {};
    setContext('metric-config', metricConfig);

    let clusters = null;
    let filterRanges = null;
    fetchClusters(metricConfig, metricUnits)
        .then(res => {
            clusters = res.clusters;
            filterRanges = res.filterRanges;

            $statsQuery.variables.filter = { list: [ { clusterId: { eq: selectedCluster } } ] };
            $statsQuery.variables.metrics = metricsInHistograms;
            $statsQuery.context.pause = false;
        })
        .catch(err => console.error(err));

    function buildHistogramData(stats, metric, numBins = 25) {
        let min = Number.MAX_VALUE, max = -min;
        for (let s of stats) {
            min = Math.min(min, s);
            max = Math.max(max, s);
        }

        min = Math.floor(min);
        max = Math.ceil(max);

        const bins = new Array(numBins).fill(0);
        for (let value of stats) {
            let x = ((value - min) / (max - min)) * (numBins - 1);
            bins[Math.floor(x)] += 1;
        }

        return {
            label: idx => {
                let x = min + (idx / (numBins - 1)) * (max - min);
                return x.toFixed(2);
            },
            bins: bins.map((count, idx) => ({ value: idx, count: count })),
            name: metric
        };
    }

    function buildScatterData(stats, metric) {
        let idx = $statsQuery.variables.metrics.indexOf(metric);
        console.assert(idx != -1, "Woops?");
        return stats[idx];
    }
</script>

<h1>Hello World!</h1>

{#if $statsQuery.error}
    <Card body color="danger" class="mb-3">Error: {$statsQuery.error.message}</Card>
{:else if !$statsQuery.data}
    <Spinner secondary />
{:else}
    <Row>
        {#each metricsInHistograms.map((metric, idx) =>
            buildHistogramData($statsQuery.data.jobMetricAverages[idx], metric)) as metric}
            <Col>
                <h2>{metric.name}</h2>
                <Histogram width={550} height={300}
                    data={metric.bins} label={metric.label} />
            </Col>
        {/each}
    </Row>
    <Row>
        {#each scatterPlotPairs as metricPair}
            <Col>
                <ScatterPlot width={550} height={300}
                   X={buildScatterData($statsQuery.data.jobMetricAverages, metricPair[0])}
                   Y={buildScatterData($statsQuery.data.jobMetricAverages, metricPair[1])}
                   xLabel={`${metricPair[0]} [${metricUnits[metricPair[0]]}]`}
                   yLabel={`${metricPair[1]} [${metricUnits[metricPair[1]]}]`} />
            </Col>
        {/each}
    </Row>
{/if}