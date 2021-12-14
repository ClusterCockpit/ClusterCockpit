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
    import ResizeableTable from '../Common/ResizeableTable.svelte';
    import RooflinePlot from '../Plots/Roofline.svelte';

    const metricConfig = {};
    $: Object.assign(metricConfig, $clustersQuery.metricConfig);
    setContext('metric-config', metricConfig);
    setContext('clusters-query', clustersQuery);

    let clusterId = null;
    let selectedMetric = "flops_any";
    let plotsPerRow = 2;
    let from = new Date(Date.now() - 30 * 60 * 1000); // new Date(Date.parse("2021-01-01T12:00:00.000"));
    let to = new Date(Date.now()); // new Date(Date.parse("2021-01-01T12:30:00.000"));
    let cluster = null;

    $: cluster = $clustersQuery.clusters && clusterId
        ? $clustersQuery.clusters.find(c => c.clusterID == clusterId)
        : null;

    let clusterSelected = false;
    $: {
        // Initialization:
        if (!$clustersQuery.fetching && !$clustersQuery.error && clusterSelected == false) {
            clusterId = window.localStorage.getItem('cc-system-view-cluster');
            if (clusterId == null || !$clustersQuery.clusters.find(c => c.clusterID == clusterId)) {
                clusterId = $clustersQuery.clusters[0].clusterID;
            }
            $nodesQuery.context.pause = false;
            $rooflineQuery.context.pause = false;
            clusterSelected = true;
        }
    }

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
    }, { pause: true });

    // TODO: FIXME: Do this calculation server side?
    // TODO: FIXME: Refetch less often?
    const rooflineQuery = operationStore(`
        query($cluster: ID!, $from: Time!, $to: Time!) {
            nodeMetrics(cluster: $cluster, nodes: null, metrics: ["flops_any", "mem_bw"], from: $from, to: $to) {
                id,
                metrics { name, data }
            }
        }
    `, {
        cluster: clusterId,
        from: from.toISOString(),
        to: to.toISOString()
    }, { pause: true });

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

        let rooflineFrom = new Date(to.getTime() - 5 * 60 * 1000)
        $rooflineQuery.variables = {
            cluster: clusterId,
            from: rooflineFrom.toISOString(), to: to.toISOString()
        };
        console.log('query:', ...Object.entries($nodesQuery.variables).flat());
    }

    query(nodesQuery);
    query(rooflineQuery);
    $: updateFilters(clusterId, selectedMetric, from, to);

    // Only take the newest value for each node for mem_bw and flops_any
    // and render it to the roofline plot.
    function rooflineData(nodeMetrics) {
        let x = new Array(), y = new Array(), c = new Array();
        for (let node of nodeMetrics) {
            const memBw = node.metrics.find(m => m.name == 'mem_bw')
            const flopsAny = node.metrics.find(m => m.name == 'flops_any')
            if (!memBw || !flopsAny || memBw.data.length < 1 || flopsAny.data.length < 1)
                continue

            const f = flopsAny.data[flopsAny.data.length - 1],
                  m = memBw.data[memBw.data.length - 1];
            const intensity = f / m;
            if (Number.isNaN(intensity) || !Number.isFinite(intensity))
                continue;

            x.push(intensity);
            y.push(f);
            c.push(0);
        }

        return {
            x, y, c,
            xLabel: 'Intensity [FLOPS/byte]',
            yLabel: 'Performance [GFLOPS]'
        };
    }

    const getNodeUrl = typeof NODEVIEW_URL !== 'undefined'
        ? NODEVIEW_URL
        : (clusterId, nodeId) => `/monitoring/node/${clusterId}/${nodeId}`;

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
        {:else if !$clustersQuery.fetching && cluster != null}
            <Resizable let:width>
                <RooflinePlot width={width} height={300}
                    cluster={cluster} colorDots={false}
                    data={rooflineData($rooflineQuery.data.nodeMetrics)} />
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
                <select class="form-select" bind:value={clusterId} on:change={() =>
                    window.localStorage.setItem('cc-system-view-cluster', clusterId)}>
                    <option value={null}>None</option>
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
        {:else if !$clustersQuery.fetching && cluster != null}
            <h5>{selectedMetric}</h5>

            <Row><Col>
                <ResizeableTable let:width let:item={node} itemsPerRow={plotsPerRow} items={$nodesQuery.data.nodeMetrics.map((node) => {
                    let m = node.metrics.find(m => m.name == selectedMetric);
                    if (m == null || m.data.length == 0)
                        return ({ id: node.id, metric: selectedMetric, data: null });

                    return {
                        id: node.id,
                        metric: m.name,
                        data: {
                            timestep: metricConfig[clusterId][selectedMetric].timestep,
                            series: [{ data: m.data }]
                        }
                    };
                })}>
                    {#if node && node.data}
                        <span class="plot-title"><a href={getNodeUrl(clusterId, node.id)}>{node.id}</a></span>
                        {#key node}
                        <TimeseriesPlot
                            metric={node.metric}
                            clusterId={clusterId}
                            data={node.data}
                            height={200}
                            width={width} />
                        {/key}
                    {:else if node}
                        <span class="plot-title">{node.id}</span>
                        <Card body color="warning">No Data</Card>
                    {/if}
                </ResizeableTable>
            </Col></Row>
        {/if}
    </Col>
</Row>
