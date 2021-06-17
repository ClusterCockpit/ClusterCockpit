<script>
    import { setContext, getContext } from 'svelte';
    import { initClient, operationStore, query } from '@urql/svelte';
    import { Spinner, Row, Col, Card } from 'sveltestrap';
    import Histogram from './Histogram.svelte';
    import ScatterPlot from './ScatterPlot.svelte';
    import { fetchClusters } from './utils.js';

    const selectedCluster = 'emmy'; // TODO: Make select/configurable
    const metricsInHistograms = ['flops_any', 'mem_bw', 'mem_used']; // TODO: Make select/configurable
    const scatterPlotPairs = [ ['flops_any', 'mem_bw'], ['mem_bw', 'mem_used'] ]; // TODO: Make select/configurable

    const clusterCockpitConfig = getContext('cc-config');

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`
    });

    const statsQuery = operationStore(`
    query($filter: JobFilterList!, $metrics: [String!]!) {
        jobMetricStatistics(filter: $filter, metrics: $metrics) {
           avg
       }
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
        stats = stats.map(s => {
            min = Math.min(min, s.avg);
            max = Math.max(max, s.avg);
            return s.avg;
        });

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
        return stats[idx].map(s => s.avg);
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
            buildHistogramData($statsQuery.data.jobMetricStatistics[idx], metric)) as metric}
            <Col>
                <h2>{metric.name}</h2>
                <Histogram width={400} height={200}
                    data={metric.bins} label={metric.label} />
            </Col>
        {/each}
    </Row>
    <Row>
        {#each scatterPlotPairs as metricPair}
            <Col>
                <ScatterPlot width={400} height={200}
                   X={buildScatterData($statsQuery.data.jobMetricStatistics, metricPair[0])}
                   Y={buildScatterData($statsQuery.data.jobMetricStatistics, metricPair[1])}
                   yLabel={`${metricPair[0]} [${metricUnits[metricPair[0]]}]`}
                   xLabel={`${metricPair[1]} [${metricUnits[metricPair[1]]}]`} />
            </Col>
        {/each}
    </Row>
{/if}