<script>
    import { setContext } from 'svelte';
    import Datatable from '../Datatable/Datatable.svelte';
    import TableControl from '../Filters/Control.svelte';
    import TableInfo from '../Filters/Info.svelte';
    import { clustersQuery } from '../Common/utils.js';

    export let filterPresets;

    const metricConfig = {};
    $: Object.assign(metricConfig, $clustersQuery.metricConfig);
    setContext('metric-config', metricConfig);
    setContext('clusters-query', clustersQuery);

    let sorting = { field: "startTime", order: "DESC" };
    let datatable;
    let filterItems = [];
    let matchedJobs;
    let appliedFilters;
    let selectedMetrics;

    if (filterPresets && filterPresets.tagId)
        filterItems.push({ tags: [ filterPresets.tagId ] });

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
    {matchedJobs}/>

<TableControl
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
    {sorting} />
