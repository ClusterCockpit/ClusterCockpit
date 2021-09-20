<script>
    import { createEventDispatcher, getContext } from "svelte";
    import { Icon, Button, ListGroup, ListGroupItem,
        Modal, ModalBody, ModalHeader, ModalFooter } from 'sveltestrap';
    import ColumnConfig from '../Common/ColumnConfig.svelte';
    import Filter from '../Filters/Filters.svelte';

    const clusterCockpitConfig = getContext('cc-config');
    const clustersQuery = getContext('clusters-query');

    export let sorting;
    export let pendingFilters;
    export let appliedFilters;
    export let filterPresets = null;
    export let limitedToUser = false;
    export let selectedMetrics = clusterCockpitConfig['plot_list_selectedMetrics'];

    let filterConfigOpen = false;
    let columnConfigOpen = false;
    let sortConfigOpen = false;

    let userFilter = '';
    const dispatch = createEventDispatcher();

    const toggleFilterConfig = () => (filterConfigOpen = !filterConfigOpen);
    const toggleColumnConfig = () => (columnConfigOpen = !columnConfigOpen);
    const toggleSortConfig = () => (sortConfigOpen = !sortConfigOpen);

    /* Run query when the user has
     * stopped typing for 350ms:
     */
    let searchTimeoutId = null;
    const searchDelay = 350;

    let sortedColumns = {
        startTime:   {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "startTime",   current: 0},
        duration:    {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "duration",    current: 2},
        numNodes:    {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "numNodes",    current: 2},
        memUsedMax:  {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "memUsedMax",  current: 2},
        flopsAnyAvg: {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "flopsAnyAvg", current: 2},
        memBwAvg:    {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "memBwAvg",    current: 2},
        netBwAvg:    {type: "numeric", direction: ["down","up"], order: ["DESC","ASC"], field: "netBwAvg",    current: 2},
    };

    function handleUserFilter(event) {
        if (searchTimeoutId !== null)
            clearTimeout(searchTimeoutId);

        searchTimeoutId = setTimeout(() => {
            dispatch("update", { userFilter: userFilter });
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

{#if $clustersQuery.metricConfig}
    <!-- ColumnConfig will want getContext('metric-config') to be initialized
         when it is initialising! -->
    <ColumnConfig
        bind:isOpen={columnConfigOpen}
        bind:selectedMetrics={selectedMetrics} />
{/if}

<Filter
    showFilters={filterConfigOpen}
    bind:appliedFilters
    bind:filters={pendingFilters}
    {filterPresets}
    on:update />

<div class="d-flex flex-row mb-2">
    <div class="me-2">
        <Button outline color=success on:click={toggleFilterConfig}><Icon name="filter" /></Button>
    </div>
    {#if !limitedToUser}
        <div class="input-group w-25 me-2" >
            <div class="input-group-prepend">
                <div class="input-group-text"><Icon name="search" /></div>
            </div>
            <input type="search" bind:value={userFilter} on:input={handleUserFilter} class="form-control"  placeholder="Filter userId" />
        </div>
    {/if}
    <div class="me-2">
        <Button outline color=primary on:click={toggleColumnConfig}><Icon name="gear" /></Button>
    </div>
    <div class="me-2">
        <Button outline on:click={toggleSortConfig}>
            {#if sorting.order == 'ASC'}
                <Icon name="sort-up"/>
            {:else if sorting.order == 'DESC'}
                <Icon name="sort-down"/>
            {/if}
            {sorting.field}
        </Button>
    </div>
</div>


