<script>
    import { initClient, operationStore, query, getClient } from '@urql/svelte';
    import { Col, Row, FormGroup,
        Label,
        Table, Icon, Badge,
        Button,
        Card, CardBody,
        Spinner,
        ListGroup, ListGroupItem,
        Modal, ModalBody, ModalHeader, ModalFooter, Input } from 'sveltestrap';
    import { setContext } from 'svelte';
    import Pagination from './Pagination.svelte';
    import Filter, { defaultFilterItems } from './FilterConfig.svelte';
    import ColumnConfig from './ColumnConfig.svelte';
    import JobMeta from './JobMeta.svelte';
    import JobMetricPlots from './JobMetricPlots.svelte';
    import { fetchClusters } from './utils.js';

    let itemsPerPage = 25;
    let page = 1;
    let filterItems = defaultFilterItems;
    let userFilter;
    let sorting = { field: "startTime", order: "DESC" };
    let paging = { itemsPerPage: itemsPerPage, page: page };
    let sortedColumns = {
        startTime:   {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "startTime",   current: 0},
        duration:    {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "duration",    current: 2},
        numNodes:    {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "numNodes",    current: 2},
        memUsedMax:  {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "memUsedMax",  current: 2},
        flopsAnyAvg: {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "flopsAnyAvg", current: 2},
        memBwAvg:    {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "memBwAvg",    current: 2},
        netBwAvg:    {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "netBwAvg",    current: 2},
    };

    let metrics = [];
    let selectedMetrics = [];

    let columnConfigOpen = false;
    let sortConfigOpen = false;
    let showFilters = false;
    const toggleColumnConfig = () => (columnConfigOpen = !columnConfigOpen);
    const toggleSortConfig = () => (sortConfigOpen = !sortConfigOpen);
    const toggleFilter = () => (showFilters = !showFilters);

    let tableWidth, plotWidth;
    let jobMetaWidth = 200;
    let rowHeight = 200;
    $: {
        const elm = document.querySelector('.cc-table-wrapper tbody td:first-child > div');
        if (elm)
            rowHeight = Math.max(200, elm.offsetHeight);

        plotWidth = Math.floor((tableWidth - jobMetaWidth) / selectedMetrics.length - 10);
    }

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`
    });

    const metricUnits = {};
    const metricConfig = {};
    setContext('metric-config', metricConfig);

    let clusters = null;
    let filterRanges = null;
    fetchClusters(metricConfig, metricUnits).then(res => {
        clusters = res.clusters;
        filterRanges = res.filterRanges;
        metrics = Object.keys(metricUnits);

        selectedMetrics = metrics
            .filter(m => clusters.every(c =>
                metricConfig[c.clusterID][m] != null))
            .slice(0, 4);
    }, err => console.error(err));

    const jobQuery = operationStore(`
    query($filter: JobFilterList!, $sorting: OrderByInput!, $paging: PageRequest! ){
       jobs(filter: $filter, order: $sorting, page: $paging) {
           items {
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
           count
         }
    }
    `, {filter: { list: defaultFilterItems }, sorting, paging});

    query(jobQuery);

    function handleFilter( event ) {
        if (event.detail && event.detail.filterItems)
            filterItems = event.detail.filterItems;

        filterItems = filterItems.filter(f => f.userId == null);
        if (userFilter)
            filterItems.push({ userId: { contains: userFilter }});

        console.info('filters:', ...filterItems.map(f => Object.entries(f).flat()).flat());

        $jobQuery.variables.filter = { "list": filterItems };
    }

    function handlePaging( event ) {
        itemsPerPage = event.detail.itemsPerPage;
        page = event.detail.page;
        $jobQuery.variables.paging = {itemsPerPage: itemsPerPage, page: page };
    }

    /* Run query when the user has
     * stopped typing for 350ms:
     */
    let searchTimeoutId = null;
    const searchDelay = 350;
    function handleUserFilter( event ) {
        if (searchTimeoutId !== null)
            clearTimeout(searchTimeoutId);

        searchTimeoutId = setTimeout(() => {
            handleFilter(event);
            searchTimeoutId = null;
        }, searchDelay);
    }

    function handleSorting( event ) {
        let nextActiveCol = event.currentTarget.id;
        const keys = Object.keys(sortedColumns);

        keys.forEach((key) => {
            if ( key === nextActiveCol ) {
                if (sortedColumns[key].current == 2) {
                    sortedColumns[key].current = 0;
                } else {
                    if (sortedColumns[key].current == 0) {
                        sortedColumns[key].current = 1;
                    } else {
                        sortedColumns[key].current = 0;
                    }
                }

                sorting = {
                    field: sortedColumns[key].field,
                    order: sortedColumns[key].order[sortedColumns[key].current]
                };
                $jobQuery.variables.sorting = sorting;
            } else {
                sortedColumns[key].current = 2;
            }
        });
    }
</script>

<style>
    .sort {
        border: none;
        margin: 0;
        padding: 0;
        background: 0 0;
        transition: all 70ms;
    }

    .active {
        background-color: #bbb;
    }

    .cc-table-wrapper {
        overflow: initial;
    }

    :global(.cc-table-wrapper > table) {
        border-collapse: separate;
        border-spacing: 0px;
        table-layout: fixed;
    }

    :global(.cc-table-wrapper > table > tbody > tr > td) {
        margin: 0px;
        padding-left: 5px;
        padding-right: 0px;
    }

    th.position-sticky.top-0 {
        background-color: white;
        z-index: 1000;
        border-bottom: 1px solid black;
    }
</style>

<Modal isOpen={sortConfigOpen} toggle={toggleSortConfig}>
    <ModalHeader>
        Sort rows
    </ModalHeader>
    <ModalBody>
        <ListGroup>
            {#each Object.keys(sortedColumns) as col}
                <ListGroupItem>
                    {#if sortedColumns[col].current == 2}
                        <button type="button" class="sort" id="{col}" on:click={handleSorting}>
                             <Icon name="sort-{sortedColumns[col].type}-{sortedColumns[col].direction[0]}"/>
                        </button>
                    {:else}
                        <button type="button" class="sort active" id="{col}" on:click={handleSorting}>
                            <Icon name="sort-{sortedColumns[col].type}-{sortedColumns[col].direction[sortedColumns[col].current]}"/>
                        </button>
                    {/if}
                    {sortedColumns[col].field}
                </ListGroupItem>
            {/each}
        </ListGroup>
    </ModalBody>
    <ModalFooter>
        <Button color="primary" on:click={toggleSortConfig}>Close</Button>
    </ModalFooter>
</Modal>

<ColumnConfig
    bind:isOpen={columnConfigOpen}
    bind:metrics={metrics}
    bind:selectedMetrics={selectedMetrics} />

<Filter {showFilters}
    clusters={clusters}
    sorting={sorting}
    filterRanges={filterRanges}
    on:update={handleFilter} />
<div class="d-flex flex-row justify-content-between">
    <div>
        <Button outline color=success  on:click={toggleFilter}><Icon name="filter" /></Button>
    </div>
    <div class="input-group w-75 mb-2 mr-sm-2">
        <div class="input-group-prepend">
            <div class="input-group-text"><Icon name="search" /></div>
        </div>
        <input type="search" bind:value={userFilter} on:input={handleUserFilter} class="form-control"  placeholder="Filter userId">
      </div>
    <div>
        <Button outline on:click={toggleSortConfig}><Icon name="sort-down" /></Button>
        <Button outline on:click={toggleColumnConfig}><Icon name="gear" /></Button>
    </div>
</div>

{#if $jobQuery.fetching}
    <div class="d-flex justify-content-center">
        <Spinner secondary />
    </div>
{:else if $jobQuery.error}
    <Card body color="danger" class="mb-3"><h2>Error: {$jobQuery.error.message}</h2></Card>
{:else}
    <Row>
        <div class="col cc-table-wrapper" bind:clientWidth={tableWidth}>
            <Table cellspacing="0px" cellpadding="0px">
                <thead>
                    <tr>
                        <th class="position-sticky top-0" scope="col" style="width: {jobMetaWidth}px">
                            Job Info
                        </th>
                        {#each selectedMetrics as metric}
                            <th class="position-sticky top-0 text-center" scope="col"
                                style="width: {plotWidth}px">
                                {metric}
                                {#if metricUnits[metric]}
                                    [{metricUnits[metric]}]
                                {/if}
                            </th>
                        {/each}
                    </tr>
                </thead>
                <tbody>
                    {#each $jobQuery.data.jobs.items as row, i}
                        <tr>
                            <td style="width: {jobMetaWidth}px;">
                                <JobMeta job={row} />
                            </td>
                            {#if row["hasProfile"]}
                                <JobMetricPlots
                                    jobId={row["jobId"]}
                                    clusterId={row["clusterId"]}
                                    width={plotWidth}
                                    height={rowHeight}
                                    selectedMetrics={selectedMetrics} />
                            {:else}
                                <td colspan="{selectedMetrics.length}">
                                    <Card body color="warning">No Profiling Data</Card>
                                </td>
                            {/if}
                        </tr>
                    {/each}
                </tbody>
            </Table>
        </div>
    </Row>

    <Pagination
        {page}
        {itemsPerPage}
        itemText="Jobs"
        totalItems={$jobQuery.data.jobs.count}
        on:update={handlePaging}
        />
{/if}
