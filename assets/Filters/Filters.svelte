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
                enabled: false,
                from: 0, to: 0
            },
            {
                filter: 'memBwAvg',
                metric: 'mem_bw',
                name: 'Mem. Bw. (Avg)',
                enabled: false,
                from: 0, to: 0
            },
            {
                filter: 'loadAvg',
                metric: 'cpu_load',
                name: 'Load (Avg)',
                enabled: false,
                from: 0, to: 0
            },
            {
                filter: 'memUsedMax',
                metric: 'mem_used',
                name: 'Mem. Used (Max)',
                enabled: false,
                from: 0, to: 0
            }
        ],
        projectId: '',
        cluster: null,
        tags: {}
    };

    function toRFC3339({ date, time }, secs = '00') {
        return `${date}T${time}:${secs}Z`;
    }

    function getFilterItems(filters) {
        let filterItems = [];

        filterItems.push({ numNodes: {
            from: filters["numNodes"]["from"],
            to:   filters["numNodes"]["to"]
        }});

        filterItems.push({ startTime: {
            from: toRFC3339(filters["startTime"]["from"]),
            to:   toRFC3339(filters["startTime"]["to"], '59')
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

        let tags = Object.keys(filters["tags"]);
        if (tags.length > 0)
            filterItems.push({ tags });

        for (let stat of filters.statistics) {
            if (!stat.enabled)
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
    import { createEventDispatcher, getContext } from "svelte";
    import { Col, Row, FormGroup, Button, Input,
        ListGroup, ListGroupItem, Spinner } from 'sveltestrap';
    import DoubleRangeSlider from './DoubleRangeSlider.svelte';
    import Tag from '../Common/Tag.svelte';

    export let showFilters; /* Hide/Show the filters */
    export let filterPresets = null;
    export let appliedFilters = defaultFilters;

    const clustersQuery = getContext('clusters-query');
    const dispatch = createEventDispatcher();
    const deepCopy = (obj) => JSON.parse(JSON.stringify(obj));

    // Has to be called after the clusters value resolved!
    export function setCluster(clusterId) {
        filters.cluster = clusterId;
        updateRanges();
        appliedFilters = deepCopy(filters);
    }

    let filters = deepCopy(defaultFilters);

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

    function fromRFC3339(rfc3339) {
        let parts = rfc3339.split('T');
        return {
            date: parts[0],
            time: parts[1].split(':', 2).join(':')
        };
    }

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
        if (!$clustersQuery.filterRanges || !$clustersQuery.clusters)
            return;

        let ranges = filters.cluster
            ? $clustersQuery.clusters.find(c => c.clusterID == filters.cluster).filterRanges
            : $clustersQuery.filterRanges;

        currentRanges.numNodes = ranges.numNodes;

        function clamp(x, { from, to }) {
            return x < from ? from : (x < to ? x : to);
        }

        function clampTime(t, { from, to }) {
            let min = Date.parse(from);
            let max = Date.parse(to);
            let x = Date.parse(toRFC3339(t));

            return x < min
                ? fromRFC3339(from)
                : (x < max ? t : fromRFC3339(to));
        }

        function clampDuration(d, { from, to }) {
            let x = d.hours * 3600 + d.min * 60;
            return x < from
                ? secondsToHours(from)
                : (x < to ? d : secondsToHours(to));
        }

        filters.numNodes.from = clamp(filters.numNodes.from, ranges.numNodes);
        filters.numNodes.to = clamp(filters.numNodes.to, ranges.numNodes);

        filters.startTime.from = clampTime(filters.startTime.from, ranges.startTime);
        filters.startTime.to = clampTime(filters.startTime.to, ranges.startTime);

        filters.duration.from = clampDuration(filters.duration.from, ranges.duration);
        filters.duration.to = clampDuration(filters.duration.to, ranges.duration);

        for (let i in filters.statistics) {
            let stat = filters.statistics[i];
            let peak = getPeakValue(stat.metric);
            stat.from = clamp(stat.from, { from: 0, to: peak });
            stat.to = clamp(stat.to, { from: 0, to: peak });
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

        let filterRanges = $clustersQuery.filterRanges;
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
            console.assert(tag != null, `'${filterPresets.tagId}' does not exist`);
            appliedFilters.tags[tag.id] = tag;
            filters.tags[tag.id] = tag;
        }

        updateRanges($clustersQuery);
    };

    $: init($clustersQuery);

    function handleReset( ) {
        tagFilterTerm = '';
        filters = deepCopy(defaultFilters);
        appliedFilters = defaultFilters;
        handleApply();
    }

    function handleTagSelection(tag) {
        if (filters["tags"][tag.id])
            delete filters["tags"][tag.id];
        else
            filters["tags"][tag.id] = tag;

        filteredTags = filteredTags;
    }

    function handleApply( ) {
        let filterItems = getFilterItems(filters);
        appliedFilters = deepCopy(filters);
        dispatch("update", { filterItems: filterItems });
    }

    function handleNodesSlider({ detail }) {
        filters.numNodes.from = detail[0];
        filters.numNodes.to = detail[1];
    }

    function handleStatisticsSlider(stat, { detail }) {
        stat.from = detail[0];
        stat.to = detail[1];
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

    table th, table td {
        border-bottom: none;
    }
    table thead tr th:nth-child(1) {
        width: 9em;
    }
    table thead tr th:nth-child(2) {
        width: 3em;
    }
    table tbody tr td:nth-child(1), table tbody tr td:nth-child(2) {
        vertical-align: middle;
    }
</style>

{#if showFilters}
    <Row>
        <Col>
            <Row>
                <Col>
                    <h5>Start time</h5>
                </Col>
            </Row>
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
            <Row>
                <Col>
                    <h5>Duration</h5>
                </Col>
            </Row>
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
            <Row>
                <Col>
                    <h5>Number of nodes</h5>
                </Col>
            </Row>
            <Row>
                <DoubleRangeSlider on:change={handleNodesSlider}
                                   min={currentRanges.numNodes.from} max={currentRanges.numNodes.to}
                                   firstSlider={filters["numNodes"]["from"]} secondSlider={filters["numNodes"]["to"]}/>
            </Row>
        </Col>
        <Col xs="2">
            <Row>
                <Col>
                    <h5>Clusters</h5>
                </Col>
            </Row>
            <Row>
                <Col>
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
            </Row>
            <Row>
                <Col>
                    <br/>
                    <h5>Project ID</h5>
                </Col>
            </Row>
            <Row>
                <Col>
                    <input type="text"
                           bind:value={filters.projectId}
                           placeholder="Filter"
                           style="width: 100%;">
                </Col>
            </Row>
            <Row>
                <Col>
                    <br/>
                    <h5>Tags</h5>
                </Col>
            </Row>
            <Row>
                <Col>
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
        </Col>
        <Col>
            <Row>
                <Col>
                    <h5>Job Statistics</h5>
                </Col>
            </Row>
            <Row>
                <Col>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Statistic</th>
                                <th>Enabled</th>
                                <th>Range</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each filters.statistics as stat, idx (stat)}
                                <tr>
                                    <td>{stat.name}</td>
                                    <td><input type="checkbox" bind:checked={stat.enabled}></td>
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
                </Col>
            </Row>
        </Col>
    </Row>
    <div class="d-flex flex-row justify-content-center">
        <div class="p-2">
            <Button color=secondary on:click={handleReset}>Reset</Button>
        </div>
        <div class="p-2">
            <Button color=primary on:click={handleApply}>Apply</Button>
        </div>
    </div>
{/if}
