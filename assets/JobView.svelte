<script>
    import { setContext } from 'svelte';
    import { Col, Row, Card, Spinner } from 'sveltestrap';
    import { fetchClusters } from './utils.js';
    import { initClient, getClient,
             operationStore, query } from '@urql/svelte';
    import Plot from './Plot.svelte';
    import RooflinePlot from './RooflinePlot.svelte';
    import JobMeta from './JobMeta.svelte';
    import NodeStats from './NodeStats.svelte';

    export let jobId;
    export let clusterId;

    let cluster = null;
    let metrics = [];
    let job = null;
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

    /*
     * The jobById query could be replaced by
     * values provided by the twig template
     * when the server renders the page.
     */
    getClient()
        .query(`query {
            clusters {
                clusterID,
                flopRateScalar,
                flopRateSimd,
                memoryBandwidth,
                metricConfig {
                    name
                    unit
                    peak
                    normal
                    caution
                    alert
                }
            }

            jobById(jobId: "${jobId}", clusterId: "${clusterId}") {
                id
                jobId
                userId
                projectId
                clusterId
                startTime
                duration
                numNodes
                hasProfile
                tags { id, tagType, tagName }
            }
        }`)
        .toPromise()
        .then(res => {
            if (res.error)
                console.error(res.error);

            job = res.data.jobById;
            cluster = res.data.clusters
                .filter(c => c.clusterID === clusterId)[0];

            console.assert(cluster != null, 'unkown cluster');

            metricConfig[clusterId] = {};
            for (let config of cluster.metricConfig)
                metricConfig[clusterId][config.name] = config;

            metrics = Object.keys(metricConfig[clusterId]);
            $jobMetricsQuery.variables.metrics = metrics;
            $jobMetricsQuery.context.pause = false;
        });

    const plotsPerRow = 3;
    let plotWidth = (document.body.offsetWidth - 100) / plotsPerRow;

</script>

<style>
    h6 {
        margin-bottom: 5px;
        margin-top: 20px;
        text-align: center;
    }
</style>

<Row>
    <Col>
        {#if job != null}
            <JobMeta job={job} />
        {:else}
            <Spinner secondary />
        {/if}
    </Col>
    <Col>
        {#if $jobMetricsQuery.data}
            <RooflinePlot
                flopsAny={$jobMetricsQuery.data.jobMetrics
                    .find(m => m.name == 'flops_any').metric}
                memBw={$jobMetricsQuery.data.jobMetrics
                    .find(m => m.name == 'mem_bw').metric}
                cluster={cluster}
            />
        {:else}
            <Spinner secondary />
        {/if}
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
                <h6>
                    {metric.name}
                    [{metricConfig[clusterId][metric.name].unit}]
                </h6>
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
<Row>
    <Col>
        {#if $jobMetricsQuery.data}
            <NodeStats
                job={job}
                jobMetrics={$jobMetricsQuery.data.jobMetrics}/>
        {:else}
            <Spinner secondary />
        {/if}
    </Col>
</Row>
