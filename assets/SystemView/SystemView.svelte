<script>
    import { initGraphQL } from '../Common/gqlclient.js';
    import { getContext, setContext } from 'svelte';

    initGraphQL(getContext('cc-config'));

    import { clustersQuery, tilePlots } from '../Common/utils.js';
    import { operationStore, query } from '@urql/svelte';
    import { Row, Col, Card, Spinner, Icon,
             InputGroup, InputGroupText } from 'sveltestrap';
    import TimeSelection from './TimeSelection.svelte';
    import Resizable from '../Common/Resizable.svelte';
    import TimeseriesPlot from '../Plots/Timeseries.svelte';
    import RooflinePlot from '../Plots/Roofline.svelte';

    export let clusterId;

    const metricConfig = {};
    $: Object.assign(metricConfig, $clustersQuery.metricConfig);
    setContext('metric-config', metricConfig);
    setContext('clusters-query', clustersQuery);

    let selectedMetric = "flops_any";
    let plotsPerRow = 2;
    let from = new Date(Date.now() - 30 * 60 * 1000);
    let to = new Date(Date.now());
    let cluster = null;

    $: cluster = $clustersQuery.clusters && clusterId
        ? $clustersQuery.clusters.find(c => c.clusterID == clusterId)
        : null;

    const nodesQuery = operationStore(`
        query($cluster: ID!, $metrics: [String!], $from: Time!, $to: Time!) {
            nodeMetrics(cluster: $cluster, nodes: null, metrics: $metrics, from: $from, to: $to) {
                id,
                metrics { name, data }
            }
        }
    `, {
        cluster: clusterId,
        metrics: [selectedMetric],
        from: from.toISOString(),
        to: to.toISOString()
    });

    // TODO: FIXME: Do this calculation server side?
    // TODO: FIXME: Refetch less often?
    const rooflineQuery = operationStore(`
        query($cluster: ID!, $from: Time!, $to: Time!) {
            nodeMetrics(cluster: $cluster, nodes: null, metrics: ["flops_any", "mem_bw"], from: $from, to: $to) {
                id,
                metrics { name, data }
            }
        }
    `, { cluster: clusterId, from: from.toISOString(), to: to.toISOString() });

    function updateFilters() {
        if (from == null || to == null)
            return;

        // TODO: This is only a workaround, it should not even be needed.
        if ($nodesQuery.variables.cluster == clusterId
            && $nodesQuery.variables.to == to.toISOString()
            && $nodesQuery.variables.from == from.toISOString())
            return;

        $nodesQuery.variables = {
            cluster: clusterId, metrics: [selectedMetric],
            from: from.toISOString(), to: to.toISOString()
        };
        $rooflineQuery.variables = {
            cluster: clusterId,
            from: from.toISOString(), to: to.toISOString()
        };
        console.log('query:', ...Object.entries($nodesQuery.variables).flat());
    }

    query(nodesQuery);
    query(rooflineQuery);
    $: updateFilters(clusterId, selectedMetric, from, to);

    function rooflineTiles() {
        const rows = 15, cols = 30;
        let tiles = [];
        for (let i = 0; i < rows; i++)
            tiles.push(new Array(cols).fill(0));

        const [minX, maxX, minY, maxY] = [0.01, 1000, 1., cluster.flopRateSimd];
        const [lminX, lmaxX, lminY, lmaxY] = [minX, maxX, minY, maxY].map(Math.log10);

        for (let node of $rooflineQuery.data.nodeMetrics) {
            let flops = node.metrics.find(m => m.name == 'flops_any');
            let membw = node.metrics.find(m => m.name == 'mem_bw');
            if (!flops || !membw || flops.data.length != membw.data.length)
                throw new Error("TODO: Error handling...");

            for (let i = 0; i < flops.data.length; i++) {
                let f = flops.data[i], m = membw.data[i];

                if (m <= 0 || f == null || m == null)
                    continue;
                
                let x = Math.log10(f / m), y = Math.log10(f);
                if (x < lminX || x > lmaxX || y < lminY || y > lmaxY)
                    continue;

                x = Math.floor(((x - lminX) / (lmaxX - lminX)) * cols);
                y = Math.floor(((y - lminY) / (lmaxY - lminY)) * rows);
                tiles[y][x] += 1;
            }
        }

        return tiles;
    }

</script>

<style>
    .plot-title {
        display: inline-block;
        width: 100%;
        font-weight: bold;
        text-align: center;
        padding-bottom: 5px;
    }
</style>

<Row>
    <Col>
        {#if $rooflineQuery.fetching}
            <Spinner secondary/>
        {:else if $rooflineQuery.error}
            <Card body color="danger" class="mb-3">
                <h2>Error: {$rooflineQuery.error.message}</h2>
            </Card>
        {:else if !$clustersQuery.fetching}
            <Resizable let:width>
                <RooflinePlot width={width} height={300}
                    cluster={cluster}
                    tiles={rooflineTiles($rooflineQuery)} />
            </Resizable>
        {/if}
    </Col>
</Row>

<Row>
    {#if $clustersQuery.fetching}
        <Col><Spinner secondary/></Col>
    {:else if $clustersQuery.error}
        <Col>
            <Card body color="danger" class="mb-3">
                <h2>Error: {$clustersQuery.error.message}</h2>
            </Card>
        </Col>
    {:else}
        <Col xs="auto">
            <InputGroup>
                <InputGroupText><Icon name="cpu"/></InputGroupText>
                <InputGroupText>
                    Cluster
                </InputGroupText>
                <select class="form-select" bind:value={clusterId}>
                    {#each $clustersQuery.clusters as cluster}
                        <option value={cluster.clusterID}>{cluster.clusterID}</option>
                    {/each}
                </select>
            </InputGroup>
        </Col>
        <Col xs="auto">
            <TimeSelection
                bind:from={from}
                bind:to={to} />
        </Col>
        <Col xs="auto">
            <InputGroup>
                <InputGroupText><Icon name="graph-up"/></InputGroupText>
                <select class="form-select" bind:value={selectedMetric}>
                    {#each Object.values(metricConfig[clusterId]).map(mc => mc.name) as metric}
                        <option>{metric}</option>
                    {/each}
                </select>
            </InputGroup>
        </Col>
    {/if}
</Row>

<br/>

<Row>
    <Col>
        {#if $nodesQuery.fetching}
            <Spinner secondary/>
        {:else if $nodesQuery.error}
            <Card body color="danger" class="mb-3">
                <h2>Error: {$nodesQuery.error.message}</h2>
            </Card>
        {:else if !$clustersQuery.fetching}
            <h5>{selectedMetric}</h5>

            <Row><Col>
                <table style="width: 100%; table-layout: fixed;">
                    {#each tilePlots(plotsPerRow, $nodesQuery.data.nodeMetrics.map((node) => {
                        let m = node.metrics.find(m => m.name == selectedMetric);
                        if (m == null)
                            return ({ id: node.id, metric: selectedMetric, data: null });

                        return {
                            id: node.id,
                            metric: m.name,
                            data: {
                                timestep: metricConfig[clusterId][selectedMetric].sampletime,
                                series: [{ data: m.data }]
                            }
                        };
                    })) as row}
                    <tr>
                        {#each row as node}
                        <td>
                            {#if node && node.data}
                                <span class="plot-title">{node.id}</span>
                                <Resizable let:width>
                                    {#key node}
                                    <TimeseriesPlot
                                        metric={node.metric}
                                        clusterId={clusterId}
                                        data={node.data}
                                        height={200}
                                        width={width} />
                                    {/key}
                                </Resizable>
                            {:else if node}
                                <span class="plot-title">{node.id}</span>
                                <Card body color="warning">No Data</Card>
                            {/if}
                        </td>
                        {/each}
                    </tr>
                    {/each}
                </table>
            </Col></Row>
        {/if}
    </Col>
</Row>

<Row>
    <Col>
        {#if $rooflineQuery.fetching}
            <Spinner secondary/>
        {:else if $rooflineQuery.error}
            <Card body color="danger" class="mb-3">
                <h2>Error: {$rooflineQuery.error.message}</h2>
            </Card>
        {:else if !$clustersQuery.fetching}
        <table style="width: 100%; table-layout: fixed;">
            {#each tilePlots(plotsPerRow, $rooflineQuery.data.nodeMetrics.map((node) => {
                let flops = node.metrics.find(m => m.name == 'flops_any');
                let membw = node.metrics.find(m => m.name == 'mem_bw');
                if (!flops || !membw)
                    return { id: node.id, data: null };

                return {
                    id: node.id,
                    data: {
                        flopsAny: { series: [{ data: flops.data }] },
                        memBw: { series: [{ data: membw.data }] }
                    }
                };
            })) as row}
            <tr>
                {#each row as node}
                <td>
                    {#if node && node.data}
                        <span class="plot-title">{node.id}</span>
                        <Resizable let:width>
                            {#key node}
                            <RooflinePlot
                                width={width} height={300}
                                cluster={cluster}
                                flopsAny={node.data.flopsAny}
                                memBw={node.data.memBw} />
                            {/key}
                        </Resizable>
                    {:else if node}
                        <span class="plot-title">{node.id}</span>
                        <Card body color="warning">No Data</Card>
                    {/if}
                </td>
                {/each}
            </tr>
            {/each}
        </table>
        {/if}
    </Col>
</Row>
