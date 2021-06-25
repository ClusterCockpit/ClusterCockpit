<script>
    import { setContext, getContext, onMount, tick } from 'svelte';
    import { initClient, operationStore, query } from '@urql/svelte';
    import { Spinner, Row, Col, Card, Button, Icon,
             InputGroup, InputGroupText } from 'sveltestrap';
    import Histogram from './Histogram.svelte';
    import ScatterPlot from './ScatterPlot.svelte';
    import RooflinePlot from './RooflinePlot.svelte';
    import FilterConfig from './FilterConfig.svelte';
    import FilterInfo from './DatatableInfo.svelte';
    import Resizable from './Resizable.svelte';
    import MetricSelection from './AnalysisMetricSelection.svelte';
    import { fetchClusters, tilePlots } from './utils.js';

    const clusterCockpitConfig = getContext('cc-config');

    let plotsPerRow = clusterCockpitConfig.plot_view_plotsPerRow || 3;
    let histogramBins = 50;
    let metricsToFetch = [];
    let showFilters = false;
    let filterConfig;
    let appliedFilters;
    let matchedJobs = null;
    let selectedCluster = null;
    let selectedClusterId = window.location.hash
        ? window.location.hash.substring(1)
        : null;

    let metricsInHistograms = clusterCockpitConfig.analysis_view_histogramMetrics
            || ['flops_any', 'mem_bw', 'cpu_load'];
    let metricsInScatterplots = clusterCockpitConfig.analysis_view_scatterPlotMetrics
            || [['flops_any', 'mem_bw'], ['flops_any', 'cpu_load'], ['mem_bw', 'cpu_load']];

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
        filter: { list: [] }, metrics: []
    }, { pause: true });

    query(statsQuery);

    const rooflineHeatmapQuery = operationStore(`
        query($filter: JobFilterList!, $rows: Int!, $cols: Int!,
                $minX: Float!, $minY: Float!, $maxX: Float!, $maxY: Float!) {
            rooflineHeatmap(filter: $filter, rows: $rows, cols: $cols,
                    minX: $minX, minY: $minY, maxX: $maxX, maxY: $maxY)
        }
    `, {
        filter: { list: [] },
        rows: 50, cols: 50,
        minX: 0.01, minY: 1., maxX: 1000., maxY: -1
    }, { pause: true });

    query(rooflineHeatmapQuery);

    const metaStatsQuery = operationStore(`
    query($filter: JobFilterList!) {
       jobsStatistics(filter: $filter) {
           totalJobs
           shortJobs
           totalWalltime
           totalCoreHours
           histWalltime { count, value }
           histNumNodes { count, value }
       }
    }
    `, {
        filter: { list: [] }
    }, { pause: true });

    query(metaStatsQuery);

    $: matchedJobs = $statsQuery.data
        ? $statsQuery.data.jobMetricAverages[0].length
        : null;

    $: {
        let metrics = [...metricsInHistograms];
        for (let pair of metricsInScatterplots)
            for (let metric of pair)
                if (!metrics.includes(metric))
                    metrics.push(metric);

        metrics.sort();
        if (metrics.length != metricsToFetch.length
                || metrics.reduce((equal, m, i) => equal && metricsToFetch[i] == i)) {
            $statsQuery.variables.metrics = metrics;
            metricsToFetch = metrics;
        }
    }

    const metricUnits = {};
    const metricConfig = {};
    setContext('metric-config', metricConfig);

    let clusters = null;
    let filterRanges = null;
    fetchClusters(metricConfig, metricUnits)
        .then(res => {
            clusters = res.clusters;
            filterRanges = res.filterRanges;

            if (selectedClusterId != null) {
                selectedCluster = clusters.find(c => c.clusterID == selectedClusterId);

                tick().then(() => filterConfig.setCluster(selectedClusterId));

                updateQueries([
                    { clusterId: { eq: selectedClusterId } }
                ]);
            }
        })
        .catch(err => console.error(err));

    async function updateQueries(filterItems) {
        $statsQuery.variables.filter = { list: filterItems };
        $statsQuery.context.pause = false;

        $metaStatsQuery.variables.filter = { list: filterItems };
        $metaStatsQuery.context.pause = false;

        // So that the other two queries to go out before this one.
        // Only needed in dev mode for convenience.
        await tick();

        $rooflineHeatmapQuery.variables.filter = { list: filterItems };
        $rooflineHeatmapQuery.variables.maxY = selectedCluster.flopRateSimd;
        $rooflineHeatmapQuery.context.pause = false;
    }

    function filtersChanged(event) {
        if (!clusters)
            throw new Error('clusters-GraphQL-Query not finished!');

        let filterItems = event.detail.filterItems;
        console.info('filters:', ...filterItems.map(f => Object.entries(f).flat()).flat());
        selectedClusterId = appliedFilters.cluster;
        if (selectedClusterId == null) {
            selectedCluster = null;
            return;
        }

        selectedCluster = clusters.find(c => c.clusterID == selectedClusterId);
        window.location.hash = `#${selectedClusterId}`;
        updateQueries(filterItems);
    }

    function buildHistogramData(data, metric, numBins) {
        let idx = metricsToFetch.indexOf(metric);
        console.assert(idx != -1, "Woops?");
        let stats = data[idx];

        let min = Number.MAX_VALUE, max = -min;
        for (let s of stats) {
            min = Math.min(min, s);
            max = Math.max(max, s);
        }
        max += 1; // So that we have an exclusive range.

        if (numBins == null || numBins < 3 || numBins > 300)
            numBins = 3;

        const bins = new Array(numBins).fill(0);
        for (let value of stats) {
            let x = ((value - min) / (max - min)) * numBins;
            bins[Math.floor(x)] += 1;
        }

        return {
            label: idx => {
                let start = min + (idx / numBins) * (max - min);
                let stop = min + ((idx + 1) / numBins) * (max - min);
                return `${start.toFixed(2)} - ${stop.toFixed(2)}`;
            },
            bins: bins.map((count, idx) => ({ value: idx, count: count })),
            name: metric,
            min: min,
            max: max
        };
    }

    function buildScatterData(stats, metric) {
        let idx = $statsQuery.variables.metrics.indexOf(metric);
        console.assert(idx != -1, "Woops?");
        return stats[idx];
    }
</script>

<style>
    h5 {
        text-align: center;
    }
</style>

<FilterConfig
    bind:this={filterConfig}
    {showFilters}
    {clusters}
    {filterRanges}
    bind:appliedFilters
    on:update={filtersChanged} />

<Row>
    <Col>
        <Button outline color=success
            on:click={() => (showFilters = !showFilters)}>
            <Icon name="filter" />
        </Button>

        {#if selectedClusterId != null && clusters != null}
            <MetricSelection
                bind:metricsInHistograms
                bind:metricsInScatterplots
                availableMetrics={Object.keys(metricConfig[selectedClusterId])} />
        {/if}
    </Col>
</Row>

<FilterInfo
    {appliedFilters}
    {clusters}
    {matchedJobs} />

{#if selectedClusterId == null}
    <Row>
        <Col>
            <Card body color="danger" class="mb-3">
                Please select a single cluster!
            </Card>
        </Col>
    </Row>
{/if}

<Row><Col><hr/></Col></Row>

<Row>
    <Col xs="4">
        {#if $rooflineHeatmapQuery.error}
            <Card body color="danger" class="mb-3">Error: {$rooflineHeatmapQuery.error.message}</Card>
        {:else if $rooflineHeatmapQuery.fetching}
            <Spinner secondary />
        {:else if $rooflineHeatmapQuery.data && selectedCluster}
            {#key $rooflineHeatmapQuery.data.rooflineHeatmap}
                <Resizable let:width>
                    <RooflinePlot width={width}
                        height={300} cluster={selectedCluster}
                        tiles={$rooflineHeatmapQuery.data.rooflineHeatmap} />
                </Resizable>
            {/key}
        {/if}
    </Col>

    {#if $metaStatsQuery.error}
        <Col>
            <Card body color="danger" class="mb-3">Error: {$metaStatsQuery.error.message}</Card>
        </Col>
    {:else if $metaStatsQuery.fetching}
        <Col><Spinner secondary /></Col>
    {:else if selectedClusterId != null && $metaStatsQuery.data}
        <Col xs="8">
            <Row>
                <Col style="text-align: center; font-size: 1.2rem;">
                    <b>Short Jobs:</b>
                    {$metaStatsQuery.data.jobsStatistics.shortJobs},
                    <b>Total Walltime:</b>
                    {$metaStatsQuery.data.jobsStatistics.totalWalltime},
                    <b>Total Core Hours:</b>
                    {$metaStatsQuery.data.jobsStatistics.totalCoreHours}
                </Col>
            </Row>
            <Row>
                <Col xs="6">
                    <h5>Walltime Histogram (Hours)</h5>
                    <Resizable let:width>
                    {#key $metaStatsQuery.data.jobsStatistics.histWalltime}
                        <Histogram width={width} height={250}
                            data={$metaStatsQuery.data.jobsStatistics.histWalltime} />
                    {/key}
                    </Resizable>
                </Col>
                <Col xs="6">
                    <h5>Number of Nodes</h5>
                    <Resizable let:width>
                    {#key $metaStatsQuery.data.jobsStatistics.histNumNodes}
                        <Histogram width={width} height={250}
                            data={$metaStatsQuery.data.jobsStatistics.histNumNodes} />
                    {/key}
                    </Resizable>
                </Col>
            </Row>
        </Col>
    {/if}
</Row>

<Row><Col><hr/></Col></Row>

{#if $statsQuery.error}
    <Row>
        <Col>
            <Card body color="danger" class="mb-3">Error: {$statsQuery.error.message}</Card>
        </Col>
    </Row>
{:else if $statsQuery.fetching}
    <Row>
        <Col><Spinner secondary /></Col>
    </Row>
{:else if selectedClusterId != null && $statsQuery.data}
    <table style="width: 100%; table-layout: fixed;">
    {#each tilePlots(plotsPerRow, metricsInHistograms.map((metric, idx) =>
        buildHistogramData($statsQuery.data.jobMetricAverages, metric, histogramBins))) as row}
        <tr>
            {#each row as data}
                <td>
                    {#if data}
                        <h5>{data.name} [{metricUnits[data.name]}]</h5>
                        <Resizable let:width>
                        {#key data}
                        <Histogram width={width} height={300}
                            min={data.min} max={data.max}
                            data={data.bins} label={data.label} />
                        {/key}
                        </Resizable>
                    {/if}
                </td>
            {/each}
        </tr>
    {/each}
    </table>

    <Row><Col><hr/></Col></Row>

    <table style="width: 100%; table-layout: fixed;">
    {#each tilePlots(plotsPerRow, metricsInScatterplots) as row}
        <tr>
            {#each row as pair}
                <td>
                    {#if pair}
                        <Resizable let:width>
                        {#key $statsQuery.data.jobMetricAverages}
                        <ScatterPlot width={width} height={300}
                            X={buildScatterData($statsQuery.data.jobMetricAverages, pair[0])}
                            Y={buildScatterData($statsQuery.data.jobMetricAverages, pair[1])}
                            xLabel={`${pair[0]} [${metricUnits[pair[0]]}]`}
                            yLabel={`${pair[1]} [${metricUnits[pair[1]]}]`} />
                        {/key}
                        </Resizable>
                    {/if}
                </td>
            {/each}
        </tr>
    {/each}
    </table>
{/if}
