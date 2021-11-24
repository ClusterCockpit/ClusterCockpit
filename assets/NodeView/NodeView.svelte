<script>
    import { initGraphQL } from '../Common/gqlclient.js';
    import { getContext, setContext } from 'svelte';

    initGraphQL(getContext('cc-config'));

    import { clustersQuery, tilePlots } from '../Common/utils.js';
    import { operationStore, query } from '@urql/svelte';
    import { Row, Col, Card, Spinner, Icon,
             InputGroup, InputGroupText } from 'sveltestrap';
    import TimeSelection from '../SystemView/TimeSelection.svelte';
    import Resizable from '../Common/Resizable.svelte';
    import TimeseriesPlot from '../Plots/Timeseries.svelte';

    const metricConfig = {};
    $: Object.assign(metricConfig, $clustersQuery.metricConfig);
    setContext('metric-config', metricConfig);
    setContext('clusters-query', clustersQuery);

    export let nodeId;
    export let clusterId;

    let plotsPerRow = 2;
    let cluster;
    $: cluster = $clustersQuery.clusters && clusterId
        ? $clustersQuery.clusters.find(c => c.clusterID == clusterId)
        : null;

    let from = new Date(Date.now() - 30 * 60 * 1000);
    let to = new Date(Date.now());

    const metricsQuery = operationStore(`
        query($from: Time!, $to: Time!) {
            nodeMetrics(cluster: "${clusterId}", nodes: ["${nodeId}"], metrics: null, from: $from, to: $to) {
                id,
                metrics { name, data }
            }
        }
    `, { from: from.toISOString(), to: to.toISOString() });
    query(metricsQuery);

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
                <h2>Error: {$clustersQuery.error.message}</h2>
            </Card>
        </Col>
    {:else}
        <Col xs="auto">
            <InputGroup>
                <InputGroupText><Icon name="hdd"/></InputGroupText>
                <InputGroupText>
                    {nodeId}
                </InputGroupText>
                <InputGroupText><Icon name="cpu"/></InputGroupText>
                <InputGroupText>
                    {clusterId}
                </InputGroupText>
            </InputGroup>
        </Col>
        <Col xs="auto">
            <TimeSelection
                on:change={() => $metricsQuery.variables = { from, to }}
                bind:from={from} bind:to={to} />
        </Col>
    {/if}
</Row>

<Row>
    {#if $metricsQuery.fetching || $clustersQuery.fetching}
        <Col><Spinner secondary/></Col>
    {:else if $metricsQuery.error}
        <Col>
            <Card body color="danger" class="mb-3">
                <h2>Error: {$metricsQuery.error.message}</h2>
            </Card>
        </Col>
    {:else}
    <table style="width: 100%; table-layout: fixed;">
        {#each tilePlots(plotsPerRow, $metricsQuery.data.nodeMetrics[0].metrics.map((metric) => {
            return {
                name: metric.name,
                data: {
                    timestep: metricConfig[clusterId][metric.name].sampletime,
                    series: [{ data: metric.data }]
                }
            };
        })) as row}
        <tr>
            {#each row as metric}
            <td>
                {#if metric}
                    <span class="plot-title">{metric.name} [{metricConfig[clusterId][metric.name].unit}]</span>
                    <Resizable let:width>
                        {#key metric}
                        <TimeseriesPlot
                            metric={metric.name}
                            clusterId={clusterId}
                            data={metric.data}
                            height={200}
                            width={width} />
                        {/key}
                    </Resizable>
                {/if}
            </td>
            {/each}
        </tr>
        {/each}
    </table>
    {/if}
</Row>
