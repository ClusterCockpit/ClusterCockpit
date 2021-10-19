<script>
    import { initGraphQL } from '../Common/gqlclient.js';
    import { getContext, setContext } from 'svelte';

    initGraphQL(getContext('cc-config'));

    import { operationStore, query } from '@urql/svelte';
    import { Col, Row, Table, Card, Spinner, Button, Icon } from 'sveltestrap';
    import { clustersQuery } from '../Common/utils.js';
    import Histogram from '../Plots/Histogram.svelte';
    import Datatable from '../Datatable/Datatable.svelte';
    import TableControl from '../Filters/Control.svelte';
    import TableInfo from '../Filters/Info.svelte';

    export let userInfos;

    let sorting = { field: "startTime", order: "DESC" };
    let datatable;
    let filterItems = [{ userId: { eq: userInfos.userId } }];
    let matchedJobs;
    let pendingFilters;
    let appliedFilters;
    let selectedMetrics;

    const statsQuery = operationStore(`
    query($filter: [JobFilter!]!) {
        jobsStatistics(filter: $filter) {
            totalJobs
            shortJobs
            totalWalltime
            totalCoreHours
            histWalltime { count, value }
            histNumNodes { count, value }
        }
    }
    `, { filter: [ { userId: { eq: userInfos.userId } } ] });

    query(statsQuery);

    let screenWidth = 0;
    let histogramWidth;
    $: histogramWidth = screenWidth / 3 - 10;

    function filtersChanged(event) {
        filterItems = event.detail.filterItems;
        filterItems.push({ userId: { eq: userInfos.userId }});

        $statsQuery.variables.filter = filterItems;
        $statsQuery.reexecute();
        datatable.applyFilters(filterItems);
    }

    const metricConfig = {};
    $: Object.assign(metricConfig, $clustersQuery.metricConfig);
    setContext('metric-config', metricConfig);
    setContext('clusters-query', clustersQuery);
</script>

<style>
    h5 {
        text-align: center;
    }
</style>

<Row>
    <Col>
        <div bind:clientWidth={screenWidth} style="width: 100%"><!-- Only for getting the row width --></div>
    </Col>
</Row>
<Row>
    <TableInfo
        {pendingFilters}
        {appliedFilters}
        {matchedJobs}
        {userInfos} />
</Row>
<Row>
    {#if $statsQuery.fetching}
        <Col xs="9">
            <div class="d-flex justify-content-center">
                <Spinner secondary />
            </div>
        </Col>
    {:else if $statsQuery.error}
        <Col xs="9">
            <Card body color="danger" class="mb-3"><h2>Error: {$statsQuery.error.message}</h2></Card>
        </Col>
    {:else}
        <Col>
            <Table>
                <tbody>
                    <tr>
                        <th scope="row">Total Jobs</th>
                        <td>{$statsQuery.data.jobsStatistics.totalJobs}</td>
                    </tr>
                    <tr>
                        <th scope="row">Short Jobs</th>
                        <td>{$statsQuery.data.jobsStatistics.shortJobs}</td>
                    </tr>
                    <tr>
                        <th scope="row">Total Walltime</th>
                        <td>{$statsQuery.data.jobsStatistics.totalWalltime}</td>
                    </tr>
                    <tr>
                        <th scope="row">Total Core Hours</th>
                        <td>{$statsQuery.data.jobsStatistics.totalCoreHours}</td>
                    </tr>
                </tbody>
            </Table>
        </Col>
        <Col>
            <h5>
                Walltime Histogram (Hours)
            </h5>
            {#key $statsQuery.data.jobsStatistics.histWalltime}
                <Histogram width={histogramWidth} height={200}
                    data={$statsQuery.data.jobsStatistics.histWalltime} />
            {/key}
        </Col>
        <Col>
            <h5>
                Number of Nodes
            </h5>
            {#key $statsQuery.data.jobsStatistics.histNumNodes}
                <Histogram width={histogramWidth} height={200}
                    data={$statsQuery.data.jobsStatistics.histNumNodes} />
            {/key}
        </Col>
    {/if}
</Row>
<Row>
    <Col>
        <TableControl
            bind:sorting
            bind:selectedMetrics
            bind:appliedFilters
            bind:pendingFilters
            limitedToUser=true
            on:update={filtersChanged}
            on:reload={() => datatable.reload()} />
    </Col>
</Row>
<Row>
    <Col>
        <Datatable
            bind:this={datatable}
            bind:sorting
            bind:matchedJobs
            initialFilterItems={filterItems}
            {selectedMetrics} />
    </Col>
</Row>
