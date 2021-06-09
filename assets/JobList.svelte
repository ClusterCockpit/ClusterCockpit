<script>
    import { initClient } from '@urql/svelte';
    import { setContext } from 'svelte';
    import Datatable from './Datatable.svelte';
    import { Icon, Button } from 'sveltestrap';
    import Filter from './FilterConfig.svelte';
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

    /* Run query when the user has
     * stopped typing for 350ms:
     */
    let searchTimeoutId = null;
    const searchDelay = 350;
    function handleUserFilter(event) {
        if (searchTimeoutId !== null)
            clearTimeout(searchTimeoutId);

        searchTimeoutId = setTimeout(() => {
            filtersChanged(event);
            searchTimeoutId = null;
        }, searchDelay);
    }

    function filtersChanged(event) {
        if (event.detail && event.detail.filterItems)
            filterItems = event.detail.filterItems;

        filterItems = filterItems.filter(f => f.userId == null);
        if (userFilter)
            filterItems.push({ userId: { contains: userFilter }});

        datatable.applyFilters(filterItems);
    }

</script>

<Filter {showFilters}
    clusters={clusters}
    sorting={sorting}
    filterRanges={filterRanges}
    on:update={filtersChanged} />

<div class="d-flex flex-row justify-content-between">
    <div>
        <Button outline color=success on:click={() => (showFilters = !showFilters)}><Icon name="filter" /></Button>
    </div>
    <div class="input-group w-100 mb-2 mr-sm-2" style="margin-left: 10px;">
        <div class="input-group-prepend">
            <div class="input-group-text"><Icon name="search" /></div>
        </div>
        <input type="search" bind:value={userFilter} on:input={handleUserFilter} class="form-control"  placeholder="Filter userId" />
    </div>
</div>

<Datatable
    bind:this={datatable}
    bind:sorting={sorting}
    initialFilterItems={filterItems}
    metricUnits={metricUnits} />
