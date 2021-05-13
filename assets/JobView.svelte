<script>
    import { setContext } from 'svelte';
    import { Col, Row, Card, Spinner } from 'sveltestrap';
    import { fetchClusters } from './utils.js';
    import { initClient, getClient,
             operationStore, query } from '@urql/svelte';
    import Plot from './Plot.svelte';

    export let jobId;
    export let clusterId;

    let cluster = null;
    let metrics = [];
    const metricUnits = {};
    const metricConfig = {};
    setContext('metric-config', metricConfig);

    initClient({ url: `${window.location.origin}/query/` });

    const jobMetricsQuery = operationStore(`
        query($jobId: String!, $clusterId: String, $metrics: [String]!) {
            jobMetrics(jobId: $jobId, clusterId: $clusterId, metrics: $metrics) {
                name,
                metric {
                    unit, scope, timestep,
                    series {
                        node_id
                        statistics { avg, min, max }
                        data
                    }
                }
            }
        }
    `, {
        jobId, clusterId, metrics
    }, {
        pause: true
    });
    query(jobMetricsQuery);

    getClient()
        .query(`query {
            clusters {
                clusterID,
                metricConfig {
                    name
                    unit
                    peak
                    normal
                    caution
                    alert
                }
            }
        }`)
        .toPromise()
        .then(res => {
            if (res.error)
                console.error(res.error);

            cluster = res.data.clusters
                .filter(c => c.clusterID === clusterId)[0];
            console.assert(cluster != null, 'unkown cluster');

            metricConfig[clusterId] = {};
            for (let config of cluster.metricConfig)
                metricConfig[clusterId][config.name] = config;

            metrics = Object.keys(metricConfig[clusterId]);
            $jobMetricsQuery.variables.metrics = metrics;
            $jobMetricsQuery.context.pause = false;

            console.log(metricConfig);
            console.log(metrics);
        });

    const plotsPerRow = 3;
    let plotWidth = (document.body.offsetWidth - 100) / plotsPerRow;

</script>

<style>
    h5 {
        margin-bottom: 5px;
        margin-top: 20px;
        text-align: center;
    }
</style>

<Row>
    <Col>
        JobId: {jobId}, clusterId: {clusterId}
    </Col>
</Row>
<Row>
    {#if $jobMetricsQuery.fetching}
        <Col>
            <Spinner secondary />
        </Col>
    {:else if $jobMetricsQuery.error}
        <Col>
            <Card body color="danger" class="mb-3">
                Error: {$jobMetricsQuery.error.message}
            </Card>
        </Col>
    {:else if $jobMetricsQuery.data}
        {#each $jobMetricsQuery.data.jobMetrics as metric, index}
            {#if index % plotsPerRow == 0 && index != 0}{@html '<div class="row">'}{/if}
            <Col>
                <h5>
                    {metric.name}
                    [{metricConfig[clusterId][metric.name].unit}]
                </h5>
                <Plot
                    metric={metric.name}
                    clusterId={clusterId}
                    data={metric.metric}
                    height={200}
                    width={plotWidth}/>
            </Col>
            {#if index % plotsPerRow == 0 && index != 0}{@html '</div>'}{/if}
        {:else}
            <Col>
                <Card body color="warning">No Data</Card>
            </Col>
        {/each}
    {:else}
        <Col>
            <Spinner secondary />
        </Col>
    {/if}
</Row>
