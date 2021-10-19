<script>
    import { initGraphQL } from '../Common/gqlclient.js';
    import { getContext, setContext } from 'svelte';

    initGraphQL(getContext('cc-config'));

    import { Col, Row, Card, Spinner, Button, Icon } from 'sveltestrap';
    import { tilePlots } from '../Common/utils.js';
    import { getClient } from '@urql/svelte';
    import Plot from '../Plots/Timeseries.svelte';
    import RooflinePlot from '../Plots/Roofline.svelte';
    import JobMeta from '../Datatable/JobMeta.svelte';
    import NodeStats from './NodeStats.svelte';
    import TagControl from './TagControl.svelte';
    import Zoom from './Zoom.svelte';
    import PolarPlot from '../Plots/Polar.svelte';
    import Resizable from '../Common/Resizable.svelte';
    import ColumnConfig from '../Common/ColumnConfig.svelte';

    export let jobInfos;

    const { clusterId } = jobInfos;
    const clusterCockpitConfig = getContext('cc-config');

    let fetching = true;
    let cluster = null;
    let metrics = null;
    let job = null;
    let allTags = null;
    let jobMetrics = null;
    let queryError = null;
    let plotHeight = 400;
    let metricSelectionOpen = false;
    let selectedMetrics = clusterCockpitConfig.job_view_selectedMetrics != null
        ? clusterCockpitConfig.job_view_selectedMetrics
        : ['flops_any', 'mem_bw', 'mem_used'];
    const polarPlotMetrics = clusterCockpitConfig.job_view_polarPlotMetrics != null
        ? clusterCockpitConfig.job_view_polarPlotMetrics
        : [ 'flops_any',  'mem_bw', 'mem_used', 'net_bw', 'file_bw' ];
    const plotsPerRow = clusterCockpitConfig.plot_view_plotsPerRow;

    const metricConfig = {};
    setContext('metric-config', metricConfig);

    let timeseriesPlots = {};

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
                state
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
        <Col xs="4">
            <JobMeta job={job} />
            <br/>

            <TagControl bind:job={job} allTags={allTags} />

            <ColumnConfig
                configName="job_view_selectedMetrics"
                bind:isOpen={metricSelectionOpen}
                bind:selectedMetrics={selectedMetrics} />

            <Button outline color="secondary"
                on:click={() => (metricSelectionOpen = !metricSelectionOpen)}>
                Select Metrics
                <Icon name="graph-up" />
            </Button>
        </Col>
        <Col xs="4">
            {#if clusterCockpitConfig.plot_view_showPolarplot}
                <Resizable let:width>
                <PolarPlot
                    metrics={polarPlotMetrics}
                    cluster={cluster} jobMetrics={jobMetrics}
                    width={width} height={plotHeight} />
                </Resizable>
            {/if}
        </Col>
        <Col xs="4">
            {#if clusterCockpitConfig.plot_view_showRoofline}
                <Resizable let:width>
                <RooflinePlot
                    flopsAny={jobMetrics.find(m => m.name == 'flops_any').metric}
                    memBw={jobMetrics.find(m => m.name == 'mem_bw').metric}
                    cluster={cluster} width={width} height={plotHeight} />
                </Resizable>
            {/if}
        </Col>
    </Row>
    <Row>
        <Col>
            <Zoom timeseriesPlots={timeseriesPlots} />
        </Col>
    </Row>
    <br/>
    <table style="width: 100%; table-layout: fixed;">
    {#each tilePlots(plotsPerRow, selectedMetrics.map(metric =>
            jobMetrics.find(m => m.name == metric) || { name: metric })) as row}
        <tr>
            {#each row as metric}
                <td>
                {#if metric && !metric.metric}
                    <span class="plot-title">
                        {metric.name} [{metricConfig[clusterId][metric.name].unit}]
                    </span>
                    <br>
                    <Card body color="warning">No Profiling Data</Card>
                {:else if metric && metric.metric}
                    <span class="plot-title">
                        {metric.name} [{metricConfig[clusterId][metric.name].unit}]
                    </span>
                    <Resizable let:width>
                    <Plot
                        bind:this={timeseriesPlots[metric.name]}
                        metric={metric.name}
                        clusterId={clusterId}
                        data={metric.metric}
                        height={200}
                        width={width} />
                    </Resizable>
                {/if}
                </td>
            {/each}
        </tr>
    {/each}
    </table>

    {#if clusterCockpitConfig.plot_view_showStatTable}
        <br/>
        <Row>
            <Col>
                <NodeStats job={job} jobMetrics={jobMetrics} />
            </Col>
        </Row>
    {/if}
{/if}
