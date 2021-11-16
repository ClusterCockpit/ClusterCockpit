<script>
    import { initGraphQL } from '../Common/gqlclient.js';
    import { getContext, setContext } from 'svelte';

    initGraphQL(getContext('cc-config'));

    import { clustersQuery, tilePlots } from '../Common/utils.js';
    import { operationStore, query } from '@urql/svelte';
    import { Row, Col, Card, Spinner } from 'sveltestrap';
    import Resizable from '../Common/Resizable.svelte';
    import TimeseriesPlot from '../Plots/Timeseries.svelte';

    export let clusterId;

    const metricConfig = {};
    $: Object.assign(metricConfig, $clustersQuery.metricConfig);
    setContext('metric-config', metricConfig);
    setContext('clusters-query', clustersQuery);

    let plotsPerRow = 2;
    let metrics = ["cpu_load"];
    let from = new Date(Date.now() - 1 * 60 * 1000);
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
        metrics: metrics,
        from: from.toISOString(),
        to: to.toISOString()
    });

    query(nodesQuery);
    $: $nodesQuery.variables = {
        cluster: clusterId, metrics: metrics,
        from: from.toISOString(),
        to: to.toISOString()
    };

    $: console.log($clustersQuery);
</script>

<!-- <h1>
    TODO (cluster: {clusterId})
</h1> -->

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
        {#if $nodesQuery.fetching}
            <Spinner secondary/>
        {:else if $nodesQuery.error}
            <Card body color="danger" class="mb-3">
                <h2>Error: {$nodesQuery.error.message}</h2>
            </Card>
        {:else if !$clustersQuery.fetching}
            {#each metrics as metric}
                <h5>{metric}</h5>

                <Row><Col>
                    <table style="width: 100%; table-layout: fixed;">
                        {#each tilePlots(plotsPerRow, $nodesQuery.data.nodeMetrics) as row}
                        <tr>
                            {#each row as node}
                            <td>
                            {#if node}
                                <span class="plot-title">{node.id}</span>
                                <Resizable let:width>
                                    <TimeseriesPlot
                                        metric={metric}
                                        clusterId={clusterId}
                                        data={{
                                            timestep: metricConfig[clusterId][metric].sampletime,
                                            series: [{ data: node.metrics.find(m => m.name == metric).data }]
                                        }}
                                        height={200}
                                        width={width} />
                                </Resizable>
                            {/if}
                            </td>
                            {/each}
                        </tr>
                        {/each}
                    </table>
                </Col></Row>
            {/each}
        {/if}
    </Col>
</Row>
