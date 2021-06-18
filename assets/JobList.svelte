<script>
    import { initClient } from '@urql/svelte';
    import { setContext } from 'svelte';
    import Datatable from './Datatable.svelte';
    import { Icon, Button } from 'sveltestrap';
    import Filter from './FilterConfig.svelte';
    import TableControl from './DatatableControl.svelte';
    import TableInfo from './DatatableInfo.svelte';
    import { fetchClusters } from './utils.js';

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
        ? GRAPHQL_BACKEND
        : `${window.location.origin}/query`
    });

    let showFilters = false;
    let userFilter = '';
    let sorting = { field: "startTime", order: "DESC" };
    let datatable;
    let filterItems = [];
    let matchedJobs;
    let columnConfigOpen = false;
    let sortConfigOpen = false;
    let appliedFilters;
    let selectedMetrics;

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

    let initialFilterTagId = null;
    if (window.location.hash.startsWith('#tag=')) {
        initialFilterTagId = window.location.hash.substring(5);
        filterItems.push({ tags: [ initialFilterTagId ] });
    }

    function filtersChanged(event) {
        if (event.detail && event.detail.filterItems) {
            filterItems = event.detail.filterItems;
        }
        if (event.detail && event.detail.appliedFilters) {
            appliedFilters = event.detail.appliedFilters;
        }

        filterItems = filterItems.filter(f => f.userId == null);

        if (event.detail && event.detail.userFilter) {
            filterItems.push({ userId: { contains: event.detail.userFilter }});
        }

        datatable.applyFilters(filterItems);
    }
</script>

<TableInfo
    {appliedFilters}
    {clusters}
    {matchedJobs}/>

<TableControl
    bind:filterConfigOpen={showFilters}
    {clusters}
    {metricUnits}
    bin:sorting={sorting}
    bind:selectedMetrics={selectedMetrics}
    on:update={filtersChanged} />

<Datatable
    bind:this={datatable}
    {sorting}
    bind:matchedJobs={matchedJobs}
    initialFilterItems={filterItems}
    {selectedMetrics}
    {metricUnits} />
