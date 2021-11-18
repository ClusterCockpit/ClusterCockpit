<script>
    import { initGraphQL } from '../Common/gqlclient.js';
    import { getContext, setContext } from 'svelte';

    initGraphQL(getContext('cc-config'));

    import { clustersQuery, tilePlots } from '../Common/utils.js';
    import { operationStore, query } from '@urql/svelte';
    import { Row, Col, Card, Spinner, Icon,
             Input, InputGroup, InputGroupText } from 'sveltestrap';
    import Resizable from '../Common/Resizable.svelte';
    import TimeseriesPlot from '../Plots/Timeseries.svelte';

    export let clusterId;

    const metricConfig = {};
    $: Object.assign(metricConfig, $clustersQuery.metricConfig);
    setContext('metric-config', metricConfig);
    setContext('clusters-query', clustersQuery);

    let selectedTimeRange = 30 * 60;
    let selectedMetric = "flops_any";
    let plotsPerRow = 2;
    let from = new Date(Date.now() - selectedTimeRange * 1000);
    let to = new Date(Date.now());

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

    function updateFilters() {
        if (from == null || to == null)
            return;

        $nodesQuery.variables = {
            cluster: clusterId, metrics: [selectedMetric],
            from: from.toISOString(), to: to.toISOString()
        };
        console.log('query:', ...Object.entries($nodesQuery.variables).flat());
    }

    function updateExplicitRimeRange(type, event) {
        let d = new Date(Date.parse(event.target.value));
        if (type == 'from') from = d;
        else                to = d;
    }

    query(nodesQuery);
    $: updateFilters(clusterId, selectedMetric, from, to);

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
    {#if $clustersQuery.fetching}
        <Col><Spinner secondary/></Col>
    {:else if $clustersQuery.error}
        <Col>
            <Card body color="danger" class="mb-3">
                <h2>Error: {$nodesQuery.error.message}</h2>
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
            <InputGroup>
                <InputGroupText><Icon name="clock-history"/></InputGroupText>
                <InputGroupText>
                    Time
                </InputGroupText>
                <select class="form-select" bind:value={selectedTimeRange} on:change={(event) => {
                    if (selectedTimeRange == -1) {
                        from = null;
                        to = null;
                        return;
                    }

                    let now = Date.now(), t = selectedTimeRange * 1000;
                    from = new Date(now - t);
                    to = new Date(now);
                }}>
                    <option value={-1}>Custom</option>
                    <option value={30 * 60} selected>Last half hour</option>
                    <option value={60 * 60}>Last hour</option>
                    <option value={2 * 60 * 60}>Last 2hrs</option>
                    <option value={4 * 60 * 60}>Last 4hrs</option>
                    <option value={24 * 60 * 60}>Last day</option>
                    <option value={7 * 24 * 60 * 60}>Last week</option>
                </select>
                {#if selectedTimeRange == -1}
                    <InputGroupText>from</InputGroupText>
                    <Input type="datetime-local" on:change={(event) => updateExplicitRimeRange('from', event)}></Input>
                    <InputGroupText>to</InputGroupText>
                    <Input type="datetime-local" on:change={(event) => updateExplicitRimeRange('to', event)}></Input>
                {/if}
            </InputGroup>
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

<br/><br/><br/>

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
                                    <TimeseriesPlot
                                        metric={node.metric}
                                        clusterId={clusterId}
                                        data={node.data}
                                        height={200}
                                        width={width} />
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
