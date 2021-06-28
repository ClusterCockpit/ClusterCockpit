<script>
    import { initClient } from '@urql/svelte';
    import { setContext } from 'svelte';
    import Datatable from './Datatable.svelte';
    import TableControl from './DatatableControl.svelte';
    import TableInfo from './DatatableInfo.svelte';
    import { fetchClusters } from './utils.js';

    export let filterPresets;

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
        ? GRAPHQL_BACKEND
        : `${window.location.origin}/query`
    });

    let sorting = { field: "startTime", order: "DESC" };
    let datatable;
    let filterItems = [];
    let matchedJobs;
    let appliedFilters;
    let selectedMetrics;

    if (filterPresets && filterPresets.tagId)
        filterItems.push({ tags: [ filterPresets.tagId ] });

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

    function filtersChanged(event) {
        if (event.detail && event.detail.filterItems) {
            filterItems = event.detail.filterItems;
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
    {clusters}
    {metricUnits}
    {filterRanges}
    {filterPresets}
    bind:appliedFilters
    bind:sorting
    bind:selectedMetrics
    on:update={filtersChanged} />

<Datatable
    bind:this={datatable}
    bind:matchedJobs
    initialFilterItems={filterItems}
    {selectedMetrics}
    {metricUnits}
    {sorting} />
