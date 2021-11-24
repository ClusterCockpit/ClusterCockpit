<script context="module">
    /* The values in here are only
    * used while the GraphQL clusters
     * query is still loading. After that,
     * the values are replaced.
     */
    export const defaultFilters = {
        numNodes: {
            from: 0, to: 0
        },
        duration: {
            from: { hours: 0, min: 0 },
            to: { hours: 0, min: 0 }
        },
        startTime: {
            from: { date: "0000-00-00" , time: "00:00"},
            to: { date:  "0000-00-00", time: "00:00"}
        },
        statistics: [
            {
                filter: 'flopsAnyAvg',
                metric: 'flops_any',
                name: 'Flops Any (Avg)',
                changed: false,
                from: 0, to: 0
            },
            {
                filter: 'memBwAvg',
                metric: 'mem_bw',
                name: 'Mem. Bw. (Avg)',
                changed: false,
                from: 0, to: 0
            },
            {
                filter: 'loadAvg',
                metric: 'cpu_load',
                name: 'Load (Avg)',
                changed: false,
                from: 0, to: 0
            },
            {
                filter: 'memUsedMax',
                metric: 'mem_used',
                name: 'Mem. Used (Max)',
                changed: false,
                from: 0, to: 0
            }
        ],
        isRunning: null,
        projectId: '',
        userId: null,
        cluster: null,
        tags: {}
    };

    function toRFC3339({ date, time }, secs = 0) {
        const dparts = date.split('-');
        const tparts = time.split(':');
        const d = new Date(
            Number.parseInt(dparts[0]),
            Number.parseInt(dparts[1]) - 1,
            Number.parseInt(dparts[2]),
            Number.parseInt(tparts[0]),
            Number.parseInt(tparts[1]), secs);
        return d.toISOString();
    }

    function fromRFC3339(rfc3339) {
        const d = new Date(rfc3339);
        const pad = (n) => n.toString().padStart(2, '0');
        const date = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        const time = `${pad(d.getHours())}:${pad(d.getMinutes())}`;
        return { date, time };
    }

    function getFilterItems(filters) {
        let filterItems = [];

        filterItems.push({ numNodes: {
            from: filters["numNodes"]["from"],
            to:   filters["numNodes"]["to"]
        }});

        filterItems.push({ startTime: {
            from: toRFC3339(filters["startTime"]["from"]),
            to:   toRFC3339(filters["startTime"]["to"], 59)
        }});

        let from = filters["duration"]["from"]["hours"] * 3600
                + filters["duration"]["from"]["min"] * 60;
        let to = filters["duration"]["to"]["hours"] * 3600
                + filters["duration"]["to"]["min"] * 60;
        filterItems.push({ duration: { from: from , to: to } });

        if (filters.cluster != null)
            filterItems.push({ clusterId: { eq: filters.cluster } });

        if (filters.projectId)
            filterItems.push({ projectId: { contains: filters.projectId } });

        if (filters.isRunning != null)
            filterItems.push({ isRunning: filters.isRunning });

        if (filters.userId != null)
            filterItems.push({ userId: filters.userId });

        let tags = Object.keys(filters["tags"]);
        if (tags.length > 0)
            filterItems.push({ tags });

        for (let stat of filters.statistics) {
            if (!stat.changed)
                continue;

            filterItems.push({
                [stat.filter]: {
                    from: stat.from,
                    to: stat.to
                }
            });
        }

        return filterItems;
    }

    export const defaultFilterItems = [];
</script>

<script>
    import { fuzzySearchTags } from '../Common/utils.js';
    import { tick, createEventDispatcher, getContext } from "svelte";
    import { Col, Row, FormGroup, Button, Input, InputGroup, InputGroupText,
        TabContent, TabPane, ListGroup, ListGroupItem } from 'sveltestrap';
    import DoubleRangeSlider from './DoubleRangeSlider.svelte';
    import Tag from '../Common/Tag.svelte';

    export let showFilters; /* Hide/Show the filters */
    export let filterPresets = null;
    export let appliedFilters = defaultFilters;
    export let availableFilters = { userId: false };

    const clustersQuery = getContext('clusters-query');
    const dispatch = createEventDispatcher();
    const deepCopy = (obj) => JSON.parse(JSON.stringify(obj));

    // Has to be called after the clusters value resolved!
    export function setCluster(clusterId) {
        filters.cluster = clusterId;
        updateRanges();
        appliedFilters = deepCopy(filters);
    }

    export function getFilters() {
        return getFilterItems(filters);
    }

    export let filters = deepCopy(defaultFilters);

    let pendingChange = false;
    let globalFilterRanges = null;
    let tagFilterTerm = '';
    let filteredTags = [];
    let currentRanges = {
        numNodes: { from: 0, to: 0 },
        statistics: [
            { from: 0, to: 0 },
            { from: 0, to: 0 },
            { from: 0, to: 0 },
            { from: 0, to: 0 }
        ]
    };

    $: filteredTags = fuzzySearchTags(tagFilterTerm, $clustersQuery && $clustersQuery.tags);

    function secondsToHours(duration) {
        const hours = Math.floor(duration / 3600);
        duration -= hours * 3600;
        const min = Math.floor(duration / 60);
        return { hours, min };
    }

    function getPeakValue(metric) {
        if (filters.cluster)
            return $clustersQuery.metricConfig[filters.cluster][metric].peak;

        return $clustersQuery.clusters.reduce((max, c) =>
            Math.max(max, $clustersQuery.metricConfig[c.clusterID][metric].peak), 0);
    }

    /* Gets called when a cluster is selected
     * and once the filterRanges have been loaded (via GraphQL).
     */
    function updateRanges() {
        if (!$clustersQuery.clusters)
            return;

        let ranges = filters.cluster
            ? $clustersQuery.clusters.find(c => c.clusterID == filters.cluster).filterRanges
            : globalFilterRanges;

        currentRanges.numNodes = ranges.numNodes;
        filters.numNodes.from  = ranges.numNodes.from;
        filters.numNodes.to    = ranges.numNodes.to;
        filters.startTime.from = fromRFC3339(ranges.startTime.from);
        filters.startTime.to   = fromRFC3339(ranges.startTime.to);
        filters.duration.from  = secondsToHours(ranges.duration.from);
        filters.duration.to    = secondsToHours(ranges.duration.to);

        for (let i in filters.statistics) {
            let stat = filters.statistics[i];
            let peak = getPeakValue(stat.metric);
            stat.changed = false;
            stat.from = 0;
            stat.to = peak;
            currentRanges.statistics[i].to = peak;
        }
    }

    // For whatever reason, the generated code for the UI
    // elements calls `$$invalidate(*, $clustersQuery)`, so
    // thats why we need this variable. Might not be needed once
    // Svelte stops invalidanting.
    let initCalled = false;
    const init = () => {
        if (!$clustersQuery.clusters || initCalled)
            return;

        initCalled = true;

        console.assert($clustersQuery.clusters.length > 0, 'Whoops');

        // If the startTime upper bound is uninitialized, set one here:
        for (let i = 0; i < $clustersQuery.clusters.length; i++) {
            let fr = $clustersQuery.clusters[i].filterRanges;
            if (fr.startTime.to == null || Date.parse(fr.startTime.to) == 0) {
                let d = new Date();
                d.setHours(24, 0, 0, 0);
                fr.startTime.to = d.toISOString();
            }
        }

        let fr = $clustersQuery.clusters[0].filterRanges;
        globalFilterRanges = {
            numNodes: { from: fr.numNodes.from, to: fr.numNodes.to },
            startTime: { from: fr.startTime.from, to: fr.startTime.to },
            duration: { from: fr.duration.from, to: fr.duration.to },
        };

        for (let i = 1; i < $clustersQuery.clusters.length; i++) {
            let fr = $clustersQuery.clusters[i].filterRanges;
            globalFilterRanges.numNodes.from = Math.min(globalFilterRanges.numNodes.from, fr.numNodes.from);
            globalFilterRanges.numNodes.to = Math.max(globalFilterRanges.numNodes.to, fr.numNodes.to);
            globalFilterRanges.startTime.from = Date.parse(globalFilterRanges.startTime.from) < Date.parse(fr.startTime.from)
                    ? globalFilterRanges.startTime.from : fr.startTime.from;
            globalFilterRanges.startTime.to = Date.parse(globalFilterRanges.startTime.to) > Date.parse(fr.startTime.to)
                    ? globalFilterRanges.startTime.to : fr.startTime.to;
            globalFilterRanges.duration.from = Math.min(globalFilterRanges.duration.from, fr.duration.from);
            globalFilterRanges.duration.to = Math.max(globalFilterRanges.duration.to, fr.duration.to);
        }

        let filterRanges = globalFilterRanges;
        defaultFilters.numNodes.from = filterRanges.numNodes.from;
        defaultFilters.numNodes.to = filterRanges.numNodes.to;
        defaultFilters.startTime.from = fromRFC3339(filterRanges.startTime.from);
        defaultFilters.startTime.to = fromRFC3339(filterRanges.startTime.to);
        defaultFilters.duration.from = secondsToHours(filterRanges.duration.from);
        defaultFilters.duration.to = secondsToHours(filterRanges.duration.to);

        for (let stat of defaultFilters.statistics)
            stat.to = getPeakValue(stat.metric);

        appliedFilters = defaultFilters;
        filters = deepCopy(defaultFilters);

        if (filterPresets && filterPresets.tagId != null) {
            let tag = $clustersQuery.tags.find(tag => tag.id == filterPresets.tagId);
            console.assert(tag != null, `Tag '${filterPresets.tagId}' does not exist`);
            appliedFilters.tags[tag.id] = tag;
            filters.tags[tag.id] = tag;
        }

        if (filterPresets && filterPresets.clusterId != null) {
            console.assert($clustersQuery.clusters.find(c => c.clusterID == filterPresets.clusterId) != null,
                    `Cluster '${filterPresets.clusterId}' does not exist`);
            appliedFilters.cluster = filterPresets.clusterId;
            filters.cluster = filterPresets.clusterId;
        }

        if (filterPresets && filterPresets.isRunning != null) {
            appliedFilters.isRunning = filterPresets.isRunning;
            filters.isRunning = filterPresets.isRunning;
        }

        if (filterPresets && filterPresets.startTime) {
            appliedFilters.startTime.from = fromRFC3339(filterPresets.startTime.from);
            appliedFilters.startTime.to = fromRFC3339(filterPresets.startTime.to);
            filters.startTime.from = appliedFilters.startTime.from;
            filters.startTime.to = appliedFilters.startTime.to;
        }

        updateRanges($clustersQuery);
        tick().then(() => pendingChange = false);
    };

    $: init($clustersQuery);
    $: pendingChange = filters == filters;

    function handleApply( ) {
        let filterItems = getFilterItems(filters);
        appliedFilters = deepCopy(filters);
        dispatch("update", { filterItems: filterItems });
        tick().then(() => pendingChange = false);
    }

    function handleReset( ) {
        tagFilterTerm = '';
        filters = deepCopy(defaultFilters);
        appliedFilters = defaultFilters;
        handleApply();
    }

    function handleUndo() {
        filters = deepCopy(appliedFilters);
        tick().then(() => pendingChange = false);
    }

    function handleTagSelection(tag) {
        if (filters["tags"][tag.id]) {
            // delete does not trigger reactivity/`$$invalidate`.
            filters["tags"][tag.id] = undefined;
            delete filters["tags"][tag.id];
        } else {
            filters["tags"][tag.id] = tag;
        }
    }

    function handleNodesSlider({ detail }) {
        filters.numNodes.from = detail[0];
        filters.numNodes.to = detail[1];
    }

    function handleStatisticsSlider(stat, { detail }) {
        stat.changed = true;
        stat.from = detail[0];
        stat.to = detail[1];
        filters.statistics = filters.statistics;
    }
</script>

<style>
    .list-group.tags-list span {
        cursor: pointer;
    }

    .tags-list {
        height: 10em;
        overflow: scroll;
        border: 1px solid #ccc;
    }

    .tags-search-input {
        width: 100%;
        margin-top: 20px;
    }

    table td {
        border-bottom: none;
    }

    table tbody tr td:nth-child(1) {
        vertical-align: middle;
    }

    :global(.tab-content > .nav-tabs > .nav-item > a:not(.active)) {
        color: #848484;
    }
</style>

{#if showFilters}
    <TabContent>
        <TabPane tabId="filter-start-time-duration" tab="Start Time & Duration" active>
            <Row style="height: 1rem;"></Row>
            <Row>
                <Col xs="2"><h5>Job State</h5></Col>
                <Col><h5>Start Time</h5></Col>
                <Col><h5>Duration</h5></Col>
            </Row>
            <Row>
                <Col xs="2">
                    <ListGroup>
                        <ListGroupItem>
                            <input type="radio" bind:group={filters["isRunning"]} value={null} /> Any
                        </ListGroupItem>
                        <ListGroupItem>
                            <input type="radio" bind:group={filters["isRunning"]} value={true} /> Running
                        </ListGroupItem>
                        <ListGroupItem>
                            <input type="radio" bind:group={filters["isRunning"]} value={false} /> Finished
                        </ListGroupItem>
                    </ListGroup>
                </Col>
                <Col>
                    <p>From</p>
                    <Row>
                        <FormGroup class="col">
                            <Input type="date" name="date"  bind:value={filters["startTime"]["from"]["date"]}  placeholder="datetime placeholder" />
                        </FormGroup>
                        <FormGroup class="col">
                            <Input type="time" name="date"  bind:value={filters["startTime"]["from"]["time"]}  placeholder="datetime placeholder" />
                        </FormGroup>
                    </Row>
                    <p>To</p>
                    <Row>
                        <FormGroup class="col">
                            <Input type="date" name="date"  bind:value={filters["startTime"]["to"]["date"]}  placeholder="datetime placeholder" />
                        </FormGroup>
                        <FormGroup class="col">
                            <Input type="time" name="date"  bind:value={filters["startTime"]["to"]["time"]}  placeholder="datetime placeholder" />
                        </FormGroup>
                    </Row>
                </Col>
                <Col>
                    <p>Between</p>
                    <Row>
                        <Col>
                            <div class="input-group mb-2 mr-sm-2">
                                <input type="number" class="form-control"  bind:value={filters["duration"]["from"]["hours"]} >
                                <div class="input-group-append">
                                    <div class="input-group-text">h</div>
                                </div>
                            </div>
                        </Col>
                        <Col>
                            <div class="input-group mb-2 mr-sm-2">
                                <input type="number" class="form-control" bind:value={filters["duration"]["from"]["min"]} >
                                <div class="input-group-append">
                                    <div class="input-group-text">m</div>
                                </div>
                            </div>
                        </Col>
                        <p>and</p>
                        <Col>
                            <div class="input-group mb-2 mr-sm-2">
                                <input type="number" class="form-control" bind:value={filters["duration"]["to"]["hours"]}  >
                                <div class="input-group-append">
                                    <div class="input-group-text">h</div>
                                </div>
                            </div>
                        </Col>
                        <Col>
                            <div class="input-group mb-2 mr-sm-2">
                                <input type="number" class="form-control" bind:value={filters["duration"]["to"]["min"]}  >
                                <div class="input-group-append">
                                    <div class="input-group-text">m</div>
                                </div>
                            </div>
                        </Col>
                    </Row>
                </Col>
            </Row>
        </TabPane>
        <TabPane tabId="filter-nodes-project" tab="{availableFilters.userId ? 'Nodes, Project & User' : 'Nodes & Project'}">
            <Row style="height: 1rem;"></Row>
            <Row>
                <Col>
                    <h5>Number of Nodes</h5>
                    <DoubleRangeSlider on:change={handleNodesSlider}
                        min={currentRanges.numNodes.from} max={currentRanges.numNodes.to}
                        firstSlider={filters["numNodes"]["from"]} secondSlider={filters["numNodes"]["to"]}/>
                </Col>
                <Col>
                    <h5>Project ID</h5>
                    <input type="text"
                           bind:value={filters.projectId}
                           placeholder="Project ID"
                           style="width: 100%;">

                    {#if availableFilters.userId}
                        <h5>User Id</h5>
                        <InputGroup>
                            <Input type="text" placeholder="User Id"
                                on:change={(e) => {
                                    let checkbox = e.target.parentElement.children[2].children[0];
                                    filters.userId = checkbox.checked
                                        ? { eq: e.target.value }
                                        : { contains: e.target.value };
                                }} />
                            <InputGroupText>
                                Exact Match:
                            </InputGroupText>
                            <InputGroupText>
                                <Input type="checkbox"
                                    on:change={(e) => {
                                        if (!filters.userId)
                                            return;

                                        filters.userId = filters.userId.eq
                                            ? { contains: filters.userId.eq }
                                            : { eq: filters.userId.contains };
                                    }} />
                            </InputGroupText>
                        </InputGroup>
                    {/if}
                </Col>
            </Row>
        </TabPane>
        <TabPane tabId="filer-cluster-tags" tab="Cluster & Tags">
            <Row style="height: 1rem;"></Row>
            <Row>
                <Col>
                    <h5>Clusters (Changing resets other filters)</h5>
                    <ListGroup>
                        <ListGroupItem>
                            <input type="radio" value={null}
                                   bind:group={filters["cluster"]}
                                   on:change={updateRanges} />
                            All
                        </ListGroupItem>
                        {#each ($clustersQuery.clusters || []) as cluster}
                            <ListGroupItem>
                                <input type="radio" value={cluster.clusterID}
                                       bind:group={filters["cluster"]}
                                       on:change={updateRanges} />
                                {cluster.clusterID}
                            </ListGroupItem>
                        {/each}
                    </ListGroup>
                </Col>
                <Col>
                    <h5>Tags</h5>
                    <ul class="list-group tags-list">
                        {#each filteredTags as tag}
                            <ListGroupItem class="{filters["tags"][tag.id] ? 'active' : ''}">
                                <span on:click={e => (e.preventDefault(), handleTagSelection(tag))}>
                                    <Tag tag={tag}/>
                                </span>
                            </ListGroupItem>
                        {/each}
                    </ul>
                    <input
                        class="tags-search-input" type="text"
                        placeholder="Search Tags (Click to Select)"
                        bind:value={tagFilterTerm} />
                </Col>
            </Row>
        </TabPane>
        <TabPane tabId="filter-stats" tab="Job Statistics">
            <table class="table">
                <tbody>
                    {#each filters.statistics as stat, idx (stat)}
                        <tr>
                            <td>{stat.name}</td>
                            <td>
                                <DoubleRangeSlider on:change={(e) => handleStatisticsSlider(stat, e)}
                                                   min={currentRanges.statistics[idx].from}
                                                   max={currentRanges.statistics[idx].to}
                                                   firstSlider={stat.from} secondSlider={stat.to}/>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </TabPane>
    </TabContent>
    <hr/>
    <div class="d-flex flex-row justify-content-center">
        <div class="p-2">
            <Button color=secondary on:click={handleReset}>Reset</Button>
        </div>
        <div class="p-2">
            <Button color=primary on:click={handleApply}>Apply</Button>
        </div>
        <div class="p-2">
            <Button color=primary on:click={handleUndo} disabled={!pendingChange}>Undo</Button>
        </div>
    </div>
{/if}
