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
    import TagControl from './TagControl.svelte';
    import PolarPlot from './PolarPlot.svelte';

    export let jobInfos;
    const { clusterId, jobId } = jobInfos;

    let fetching = true;
    let cluster = null;
    let metrics = null;
    let job = null;
    let allTags = null;
    let jobMetrics = null;
    let queryError = null;
    const metricConfig = {};
    setContext('metric-config', metricConfig);

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`
    });

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

            jobById(id: "${jobInfos.id}") {
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

            jobMetrics(jobId: "${jobInfos.jobId}", clusterId: "${jobInfos.clusterId}") {
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

            tags { id, tagType, tagName }
        }`)
        .toPromise()
        .then(res => {
            if (res.error) {
                console.error(res.error);
                queryError = res.error;
                fetching = false;
                return;
            }

            allTags = res.data.tags;
            job = res.data.jobById;
            cluster = res.data.clusters
                .filter(c => c.clusterID === clusterId)[0];

            console.assert(cluster != null, 'unkown cluster');

            metricConfig[clusterId] = {};
            for (let config of cluster.metricConfig)
                metricConfig[clusterId][config.name] = config;

            metrics = Object.keys(metricConfig[clusterId]);
            jobMetrics = res.data.jobMetrics.filter(m => metrics.includes(m.name));
            fetching = false;
        })
        .catch(err => {
            console.error(err);
            queryError = err;
            fetching = false;
        });

    const plotsPerRow = 3;

    function tilePlots() {
        let rows = [], i = 0;
        for (let n = 0; n < metrics.length; n += plotsPerRow) {
            let row = [];
            for (let m = 0; m < plotsPerRow; m++, i++) {
                if (i < metrics.length) {
                    let metric = jobMetrics.find(m => m.name == metrics[i]);
                    row.push(metric || { name: metrics[i] });
                } else {
                    row.push('filler');
                }
            }
            rows.push(row);
        }
        return rows;
    }

    let screenWidth = 0;
    let metricPlotWidth;
    let rooflinePlotWidth, rooflinePlotHeight = 300;
    $: metricPlotWidth = (screenWidth - 50) / plotsPerRow;
    $: rooflinePlotWidth = screenWidth / 3;
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
        <div bind:clientWidth={screenWidth} style="width: 100%"><!-- Only for getting the row width --></div>
    </Col>
</Row>
{#if fetching}
    <Row>
        <Col>
            <Spinner secondary />
        </Col>
    </Row>
{:else if queryError != null}
    <Row>
        <Col>
            <Card body color="danger" class="mb-3">
                GraphQL Query Failed: {queryError.message}
            </Card>
        </Col>
    </Row>
{:else}
    <Row>
        <Col>
            <JobMeta job={job} />
            <TagControl bind:job={job} allTags={allTags} />
        </Col>
        <Col>
            <PolarPlot
                cluster={cluster} jobMetrics={jobMetrics}
                width={rooflinePlotWidth} height={rooflinePlotHeight} />
        </Col>
        <Col>
            <RooflinePlot
                flopsAny={jobMetrics.find(m => m.name == 'flops_any').metric}
                memBw={jobMetrics.find(m => m.name == 'mem_bw').metric}
                cluster={cluster} width={rooflinePlotWidth} height={rooflinePlotHeight} />
        </Col>
    </Row>
    <br/>
    {#each tilePlots(jobMetrics) as row}
        <Row>
            {#each row as metric (metric)}
                <Col>
                {#if metric == 'filler'}
                    <!-- Filling Space -->
                {:else if !metric.metric}
                    <span class="plot-title">
                        {metric.name} [{metricConfig[clusterId][metric.name].unit}]
                    </span>
                    <br>
                    <Card body color="warning">No Profiling Data</Card>
                {:else}
                    <span class="plot-title">
                        {metric.name} [{metricConfig[clusterId][metric.name].unit}]
                    </span>
                    <Plot
                        metric={metric.name}
                        clusterId={clusterId}
                        data={metric.metric}
                        height={200}
                        width={metricPlotWidth} />
                {/if}
                </Col>
            {/each}
        </Row>
        <br/>
    {:else}
        <Row>
            <Col>
                <Card body color="warning">No Data</Card>
            </Col>
        </Row>
    {/each}
    <br/>
    <Row>
        <Col>
            <NodeStats job={job} jobMetrics={jobMetrics} />
        </Col>
    </Row>
{/if}
