<script>
    import { initGraphQL } from '../Common/gqlclient.js';
    import { getContext, setContext } from 'svelte';

    initGraphQL(getContext('cc-config'));

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
    let pendingFilters;
    let appliedFilters;
    let selectedMetrics;

    if (filterPresets && filterPresets.tagId != null)
        filterItems.push({ tags: [ filterPresets.tagId ] });

    if (filterPresets && filterPresets.clusterId != null)
        filterItems.push({ clusterId: { eq: filterPresets.clusterId } });

    if (filterPresets && filterPresets.isRunning != null)
        filterItems.push({ isRunning: filterPresets.isRunning });

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
    {pendingFilters}
    {appliedFilters}
    {matchedJobs}/>

<TableControl
    {filterPresets}
    bind:pendingFilters
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
