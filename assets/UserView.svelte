<script>
    import { initClient, operationStore, query } from '@urql/svelte';
    import { Col, Row, Table, Card, Spinner, Button, Icon } from 'sveltestrap';
    import { setContext } from 'svelte';
    import { fetchClusters } from './utils.js';
    import Histogram from './Histogram.svelte';
    import Datatable from './Datatable.svelte';
    import Filter from './FilterConfig.svelte';

    export let userInfos;

    let showFilters = false;
    let sorting = { field: "startTime", order: "DESC" };
    let datatable;
    let filterItems = [{ userId: { eq: userInfos.userId } }];

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`
    });

    const statsQuery = operationStore(`
    query($filter: JobFilterList!) {
       jobsStatistics(filter: $filter) {
           totalJobs
           shortJobs
           totalWalltime
           totalCoreHours
           histWalltime { count, value }
           histNumNodes { count, value }
       }
    }
    `, { filter: { list: [ { userId: { eq: userInfos.userId } } ] } });

    query(statsQuery);

    let screenWidth = 0;
    let histogramWidth;
    $: histogramWidth = screenWidth / 3 - 10;

    function filtersChanged(event) {
        filterItems = event.detail.filterItems;
        filterItems.push({ userId: { eq: userInfos.userId }});

        $statsQuery.variables.filter = { list: filterItems };
        datatable.applyFilters(filterItems);
    }

    const metricUnits = {};
    const metricConfig = {};
    setContext('metric-config', metricConfig);

    let clusters = null;
    let filterRanges = null;
    fetchClusters(metricConfig, metricUnits).then(res => {
        clusters = res.clusters;
        filterRanges = res.filterRanges;
        metricUnits = metricUnits;
    }, err => console.error(err));
</script>

<style>
    h5 {
        text-align: center;
    }

    input[disabled] {
        background-color: white;
        color: #999999;
    }
</style>

<Row>
    <Col>
        <div bind:clientWidth={screenWidth} style="width: 100%"><!-- Only for getting the row width --></div>
    </Col>
</Row>
<Row>
    <Col>
        <div class="d-flex flex-row justify-content-between">
            <div>
                <Button outline color=success on:click={() => (showFilters = !showFilters)}><Icon name="filter" /></Button>
            </div>
            <div class="input-group w-100 mb-2 mr-sm-2" style="margin-left: 10px;">
                <div class="input-group-prepend">
                    <div class="input-group-text"><Icon name="person-circle"/></div>
                </div>
                <input type="search" value={userInfos.userId} class="form-control" disabled />
            </div>
            {#if userInfos.name}
            <div class="input-group w-100 mb-2 mr-sm-2" style="margin-left: 10px;">
                <div class="input-group-prepend">
                    <div class="input-group-text"><Icon name="person"/></div>
                </div>
                <input type="search" value={userInfos.name} class="form-control" disabled />
            </div>
            {/if}
            {#if userInfos.email}
            <div class="input-group w-100 mb-2 mr-sm-2" style="margin-left: 10px;">
                <div class="input-group-prepend">
                    <div class="input-group-text">@</div>
                </div>
                <input type="search" value={userInfos.email} class="form-control" disabled />
            </div>
            {/if}
        </div>
    </Col>
</Row>
<Row>
    <Col>
        <hr/>
    </Col>
</Row>
<Row>
    <Col>
        <Filter {showFilters}
            clusters={clusters}
            sorting={sorting}
            filterRanges={filterRanges}
            on:update={filtersChanged} />
    </Col>
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
        <hr/>
    </Col>
</Row>
<Row>
    <Col>
        <Datatable
            bind:this={datatable}
            bind:sorting={sorting}
            initialFilterItems={filterItems}
            metricUnits={metricUnits} />
    </Col>
</Row>
