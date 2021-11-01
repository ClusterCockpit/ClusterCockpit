<script>
    import { operationStore, query, mutation } from '@urql/svelte';
    import { Row, Table, Card, Spinner } from 'sveltestrap';
    import Pagination from './Pagination.svelte';
    import JobMeta from './JobMeta.svelte';
    import RowOfPlots from './Row.svelte';
    import { getContext, onDestroy } from 'svelte';

    const clusterCockpitConfig = getContext('cc-config');
    const clustersQuery = getContext('clusters-query');

    export let sorting; /* Used as output variable if changed and initial sorting. */
    export let initialFilterItems = []; /* Can be empty, or for example used to restrict initially fetched jobs to single user. */
    export let matchedJobs; /* Used as output variable (So that it can be passed to the FilterConfig) */
    export let selectedMetrics = clusterCockpitConfig['plot_list_selectedMetrics'];

    let itemsPerPage = clusterCockpitConfig.plot_list_jobsPerPage || 10;
    let page = 1;
    let paging = { itemsPerPage: itemsPerPage, page: page };
    let tableWidth, plotWidth;
    let jobMetaWidth = 250;
    let rowHeight = 200;
    $: {
        const elm = document.querySelector('.cc-table-wrapper tbody td:first-child > div');
        if (elm)
            rowHeight = Math.max(200, elm.offsetHeight);

        plotWidth = Math.floor((tableWidth - jobMetaWidth) / selectedMetrics.length - 10);
    }

    const jobQuery = operationStore(`
    query($filter: [JobFilter!]!, $sorting: OrderByInput!, $paging: PageRequest! ){
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
                state
                tags { id, tagType, tagName }
            }
            count
        }
    }
    `, {filter: initialFilterItems, sorting, paging});

    query(jobQuery);
    $: matchedJobs = $jobQuery.data != null ? $jobQuery.data.jobs.count : 0;
    $: $jobQuery.variables = { ...$jobQuery.variables, sorting };

    const updateConfiguration = mutation({
        query: `mutation($name: String!, $value: String!) {
            updateConfiguration(name: $name, value: $value)
        }`
    });

    let prevItemsPerPage = itemsPerPage;
    function handlePaging( event ) {
        itemsPerPage = event.detail.itemsPerPage;
        page = event.detail.page;
        $jobQuery.variables.paging = { itemsPerPage: itemsPerPage, page: page };
        $jobQuery.reexecute();

        if (itemsPerPage != prevItemsPerPage) {
            prevItemsPerPage = itemsPerPage;
            updateConfiguration({
                name: "plot_list_jobsPerPage",
                value: itemsPerPage.toString()
            }).then(res => {
                if (res.error)
                    console.error(res.error);
            });
        }
    }

    export function applyFilters(filterItems) {
        console.info('filters:', ...filterItems.map(f => Object.entries(f).flat()).flat());
        $jobQuery.variables.filter = filterItems;
        $jobQuery.reexecute();
    }

    export function reload() {
        $jobQuery.reexecute({ requestPolicy: 'network-only' });
    }

    // Make datatable header stick below the app header:
    let headerPaddingTop = 10;
    const header = document.querySelector('header > nav.navbar');
    if (header) {
        // This will only really work if there is only one Datatable per page!
        // Read [this](https://developer.mozilla.org/en-US/docs/Web/API/Document/scroll_event)
        // about why there is this ticking stuff...
        let ticking = false;
        let tableHeader = null;
        const onScroll = (event) => {
            if (ticking)
                return;

            ticking = true;
            window.requestAnimationFrame(() => {
                if (!tableHeader)
                    tableHeader = document
                        .querySelector('table.table > thead > tr > th.position-sticky:nth-child(1)');

                const refPos = tableHeader.getBoundingClientRect().top;
                headerPaddingTop = refPos < header.clientHeight
                    ? (header.clientHeight - refPos) + 10
                    : 10;

                ticking = false;
            });
        };
        document.addEventListener('scroll', onScroll);
        onDestroy(() => document.removeEventListener('scroll', onScroll));
    }

</script>

<style>
    .cc-table-wrapper {
        overflow: initial;
    }

    .cc-table-wrapper > :global(table) {
        border-collapse: separate;
        border-spacing: 0px;
        table-layout: fixed;
    }

    .cc-table-wrapper :global(button) {
        margin-bottom: 0px;
    }

    .cc-table-wrapper > :global(table > tbody > tr > td) {
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
                        <th class="position-sticky top-0" scope="col"
                            style="width: {jobMetaWidth}px; padding-top: {headerPaddingTop}px">
                            Job Info
                        </th>
                        {#each selectedMetrics as metric}
                            <th class="position-sticky top-0 text-center" scope="col"
                                style="width: {plotWidth}px; padding-top: {headerPaddingTop}px">
                                {metric}
                                {#if $clustersQuery.metricUnits && $clustersQuery.metricUnits[metric]}
                                    [{$clustersQuery.metricUnits[metric]}]
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
                                <RowOfPlots
                                    job={row}
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
