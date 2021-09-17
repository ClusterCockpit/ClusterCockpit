<script>
    import { initGraphQL } from '../Common/gqlclient.js';
    import { getContext, setContext, tick } from 'svelte';

    initGraphQL(getContext('cc-config'));

    import { operationStore, query } from '@urql/svelte';
    import { Spinner, Row, Col, Card, Button, Icon,
             ListGroup, ListGroupItem,
             InputGroup, InputGroupText, Input } from 'sveltestrap';
    import Histogram from '../Plots/Histogram.svelte';
    import ScatterPlot from '../Plots/Scatter.svelte';
    import RooflinePlot from '../Plots/Roofline.svelte';
    import FilterConfig from '../Filters/Filters.svelte';
    import FilterInfo from '../Filters/Info.svelte';
    import Resizable from '../Common/Resizable.svelte';
    import PlotSelection from './PlotSelection.svelte';
    import { clustersQuery, tilePlots, formatNumber } from '../Common/utils.js';

    const clusterCockpitConfig = getContext('cc-config');

    export let filterPresets = null;

    let plotsPerRow = clusterCockpitConfig.plot_view_plotsPerRow || 3;
    let histogramBins = {};
    let metricsToFetch = [];
    let showFilters = false;
    let filterConfig;
    let pendingFilters;
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

    const metricConfig = {};
    $: Object.assign(metricConfig, $clustersQuery.metricConfig);
    setContext('metric-config', metricConfig);
    setContext('clusters-query', clustersQuery);

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

    $: matchedJobs = $metaStatsQuery.data
        ? $metaStatsQuery.data.jobsStatistics.totalJobs
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
            $statsQuery.reexecute();
            metricsToFetch = metrics;
        }
    }

    $: {
        for (let metric of metricsInHistograms)
            histogramBins[metric] = histogramBins[metric] || 50;
    }

    if (selectedClusterId != null)
        clustersQuery.subscribe(async ({ clusters }) => {
            if (!clusters)
                return;

            // Wait for filterConfig to be updated!
            await tick();

            selectedCluster = clusters.find(c => c.clusterID == selectedClusterId);
            filterConfig.setCluster(selectedClusterId);
            updateQueries(filterConfig.getFilters());
        });

    async function updateQueries(filterItems) {
        console.info('filters:', ...filterItems.map(f => Object.entries(f).flat()).flat());

        $statsQuery.variables.filter = { list: filterItems };
        $statsQuery.context.pause = false;
        $statsQuery.reexecute();

        $metaStatsQuery.variables.filter = { list: filterItems };
        $metaStatsQuery.context.pause = false;
        $metaStatsQuery.reexecute();

        // So that the other two queries to go out before this one.
        // Only needed in dev mode for convenience.
        await tick();

        $rooflineHeatmapQuery.variables.filter = { list: filterItems };
        $rooflineHeatmapQuery.variables.maxY = selectedCluster.flopRateSimd;
        $rooflineHeatmapQuery.context.pause = false;
        $rooflineHeatmapQuery.reexecute();
    }

    function filtersChanged(event) {
        let filterItems;
        if (event.detail) {
            filterItems = event.detail.filterItems;
            selectedClusterId = appliedFilters.cluster;
            if (selectedClusterId == null) {
                selectedCluster = null;
                return;
            }
        } else if (event.cluster) {
            selectedClusterId = event.cluster;
            filterConfig.setCluster(selectedClusterId);
            filterItems = filterConfig.getFilters();
        }

        selectedCluster = $clustersQuery.clusters.find(c => c.clusterID == selectedClusterId);
        window.location.hash = `#${selectedClusterId}`;
        updateQueries(filterItems);
    }

    function buildHistogramData(data, metric, numBins) {
        let idx = metricsToFetch.indexOf(metric);
        console.assert(idx != -1, "Woops?");
        let stats = data[idx];

        let min = Number.MAX_VALUE, max = -min;
        if (stats.length == 0) {
            min = 0;
            max = 0;
        } else {
            for (let s of stats) {
                min = Math.min(min, s);
                max = Math.max(max, s);
            }
            max += 1; // So that we have an exclusive range.
        }

        if (numBins == null || numBins < 3)
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
                return `${formatNumber(start)} - ${formatNumber(stop)}`;
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
        padding-left: 25px;
        padding-right: 25px;
    }
</style>

<FilterConfig
    bind:this={filterConfig}
    {showFilters}
    {filterPresets}
    bind:appliedFilters
    bind:filters={pendingFilters}
    availableFilters={{ userId: true }}
    on:update={filtersChanged} />

{#if selectedClusterId == null || $clustersQuery.error}
<Row>
    <Col>
        {#if $clustersQuery.fetching}
            <Spinner secondary />
        {:else if $clustersQuery.error}
            <Card body color="danger" class="mb-3">{$clustersQuery.error.message}</Card>
        {:else if $clustersQuery.clusters}
            <ListGroup>
                <ListGroupItem disabled>Select one of the following clusters:</ListGroupItem>
                {#each $clustersQuery.clusters.map(c => c.clusterID) as cluster}
                    <ListGroupItem>
                        <Button outline on:click={() => filtersChanged({ cluster })}>
                            {cluster}
                        </Button>
                    </ListGroupItem>
                {/each}
            </ListGroup>
        {/if}
    </Col>
</Row>
{:else}
<Row style="margin-bottom: 0.5rem;">
    <Col>
        <Button outline color=success
            on:click={() => (showFilters = !showFilters)}>
            <Icon name="filter" />
        </Button>

        {#if selectedClusterId != null && $clustersQuery.clusters != null}
            <PlotSelection
                bind:metricsInHistograms
                bind:metricsInScatterplots
                availableMetrics={Object.keys(metricConfig[selectedClusterId])} />
        {/if}
    </Col>
</Row>

<Row>
    <Col>
        <FilterInfo
            {pendingFilters}
            {appliedFilters}
            {matchedJobs} />
    </Col>
</Row>

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
        {#if $rooflineHeatmapQuery.fetching}
            <Spinner secondary />
        {:else if $rooflineHeatmapQuery.error}
            <Card body color="danger" class="mb-3">Error: {$rooflineHeatmapQuery.error.message}</Card>
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

    {#if $metaStatsQuery.fetching}
        <Col><Spinner secondary /></Col>
    {:else if $metaStatsQuery.error}
        <Col>
            <Card body color="danger" class="mb-3">Error: {$metaStatsQuery.error.message}</Card>
        </Col>
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

{#if $statsQuery.fetching}
    <Row>
        <Col><Spinner secondary /></Col>
    </Row>
{:else if $statsQuery.error}
    <Row>
        <Col>
            <Card body color="danger" class="mb-3">Error: {$statsQuery.error.message}</Card>
        </Col>
    </Row>
{:else if selectedClusterId != null && $statsQuery.data}
    <table style="width: 100%; table-layout: fixed;">
    {#each tilePlots(plotsPerRow, metricsInHistograms.map((metric, idx) =>
        buildHistogramData($statsQuery.data.jobMetricAverages, metric, histogramBins[metric]))) as row}
        <tr>
            {#each row as data}
                <td>
                    {#if data}
                        <h5>
                            {data.name} [{$clustersQuery.metricUnits[data.name]}]

                            <span style="float: right;">
                            <InputGroup size="sm">
                                <InputGroupText>
                                    Bins:
                                </InputGroupText>
                                <Input style="margin-bottom: 0px;"
                                    type="number" min="5"
                                    bind:value={histogramBins[data.name]} />
                            </InputGroup>
                            </span>
                        </h5>
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
                            xLabel={`${pair[0]} [${$clustersQuery.metricUnits[pair[0]]}]`}
                            yLabel={`${pair[1]} [${$clustersQuery.metricUnits[pair[1]]}]`} />
                        {/key}
                        </Resizable>
                    {/if}
                </td>
            {/each}
        </tr>
    {/each}
    </table>
{/if}

{/if} <!-- selectedClusterId -->
