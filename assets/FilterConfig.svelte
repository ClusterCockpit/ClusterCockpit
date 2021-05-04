<script context="module">
    export const defaultFilters = {
        numNodes: {
            from: 1, to: 64
        },
        duration: {
            from: { hours: 0, min: 10 },
            to: { hours: 24, min: 0 }
        },
        startTime: {
            from: { date: "2014-01-01" , time: "12:00"},
            to: { date:  "2021-03-30", time: "23:00"}
        }
    };

    export const defaultFilterItems = [
        {numNodes: {from: 1, to: 64}},
        {duration: {from: 600, to: 84600}},
        {startTime: {from: "2014-01-01T12:00:00Z", to: "2021-03-30T23:00:00Z"}}
    ];
</script>

<script>
    import { getColorForTag } from './utils.js';
    import { createEventDispatcher } from "svelte";
    import { Col, Row, FormGroup, Button, Input,
             ListGroup, ListGroupItem, Card, Spinner } from 'sveltestrap';
    import { operationStore, query } from '@urql/svelte';

    /* Deep clone: */
    let filters = JSON.parse(JSON.stringify(defaultFilters));

    let tagsQuery = operationStore(`
        query {
            tags {
                id,
                tagName,
                tagType
            }
        }
    `);

    query(tagsQuery);

    export let showFilters = false;
    export let clusters;
    const dispatch = createEventDispatcher();

    let tagFilterTerm = '';
    let projectFilterTerm = '';
    let filteredTags = [];
    let selectedTags = new Set();
    let selectedCluster = null;

    function fuzzyMatch(term, string) {
        return string.toLowerCase().includes(term);
    }

    function fuzzySearchTags(term, tags) {
        if (!tags)
            return;

        let results = [];
        for (let tag of tags) {
            if (fuzzyMatch(term, tag.tagType) ||
                fuzzyMatch(term, tag.tagName))
                results.push(tag);
        }
        filteredTags = results;
    }

    $: fuzzySearchTags(tagFilterTerm, $tagsQuery.data && $tagsQuery.data.tags);

    function handleReset( ) {
        tagFilterTerm = '';
        projectFilterTerm = '';
        filters = JSON.parse(JSON.stringify(defaultFilters));
        selectedTags.clear();
        selectedCluster = null;
        handleApply();
    }

    function handleTagSelection(tag) {
        if (selectedTags.has(tag))
            selectedTags.delete(tag);
        else
            selectedTags.add(tag);

        selectedTags = selectedTags;
    }

    function toTime({ date, time }) {
        return `${date}T${time}:00Z`; /* Expected: rfc3339 */
    }

    function handleApply( ) {
        let filterItems = [];
        const keys = Object.keys(filters);

        keys.forEach((key, index) => {
            switch(key) {
                case "numNodes":
                    filterItems.push({numNodes: {"from": filters["numNodes"]["from"], to:  filters["numNodes"]["to"]}});
                    break;
                case "startTime":
                    filterItems.push({ startTime: {
                        from: toTime(filters["startTime"]["from"]),
                        to: toTime(filters["startTime"]["to"])
                    } });
                    break;
                case "duration":
                    var from = filters["duration"]["from"]["hours"] * 3600 + filters["duration"]["from"]["min"] * 60;
                    var to = filters["duration"]["to"]["hours"] * 3600 + filters["duration"]["to"]["min"] * 60;
                    filterItems.push({duration: {from: from , to: to }});
            }
        });

        if (selectedTags.size > 0)
            filterItems.push({ tags: Array.from(selectedTags).map(t => t.id) });

        if (selectedCluster != null)
            filterItems.push({ clusterId: { eq: selectedCluster } });

        if (projectFilterTerm)
            filterItems.push({ projectId: { contains: projectFilterTerm } });

        dispatch("update", { filterItems });
    }
</script>

<style>
    .cc-tag.badge.rounded-pill {
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
        </Col>
        <Col>
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
        </Col>
        <Col>
            <Row>
                <Col>
                    <h5>Number of nodes</h5>
                </Col>
            </Row>
            <p>Between</p>
            <Row>
                <FormGroup class="col">
                    <Input type=number bind:value={filters["numNodes"]["from"]} min=1 max=64 />
                    <Input type=range bind:value={filters["numNodes"]["from"]} min=1 max=64 />
                </FormGroup>
                <p>and</p>
                <FormGroup class="col">
                    <Input type=number bind:value={filters["numNodes"]["to"]} min=1 max=64 />
                    <Input type=range bind:value={filters["numNodes"]["to"]} min=1 max=64 />
                </FormGroup>
            </Row>
        </Col>
    </Row>
    <Row>
        <Col>
            <Row>
                <Col>
                    <h5>Tags</h5>
                </Col>
            </Row>
            <Row>
                <Col>
                    {#if $tagsQuery.fetching}
                        <div class="d-flex justify-content-center">
                            <Spinner secondary />
                        </div>
                    {:else if $tagsQuery.error}
                        <Card body color="danger" class="mb-3"><h2>Error: {$tagsQuery.error.message}</h2></Card>
                    {:else}
                        <ul class="list-group tags-list">
                            {#each filteredTags as tag}
                                <ListGroupItem class="{selectedTags.has(tag) ? 'active' : ''}">
                                    <span class="cc-tag badge rounded-pill {getColorForTag(tag)}" on:click={_ => handleTagSelection(tag)}>
                                        {tag.tagType}: {tag.tagName}
                                    </span>
                                </ListGroupItem>
                            {/each}
                        </ul>
                        <input
                            class="tags-search-input" type="text"
                            placeholder="Search Tags (Click to Select)"
                            bind:value={tagFilterTerm}>
                    {/if}
                </Col>
            </Row>
        </Col>
        <Col>
            <Row>
                <Col>
                    <h5>Clusters</h5>
                </Col>
            </Row>
            <Row>
                <Col>
                    <ListGroup>
                        <ListGroupItem>
                            <input type="radio" bind:group={selectedCluster} value={null}/>
                            All
                        </ListGroupItem>
                        {#each clusters as cluster}
                            <ListGroupItem>
                                <input type="radio" bind:group={selectedCluster} value={cluster}/>
                                {cluster}
                            </ListGroupItem>
                        {/each}
                    </ListGroup>
                </Col>
            </Row>
        </Col>
        <Col>
            <Row>
                <Col>
                    <h5>Project ID</h5>
                </Col>
            </Row>
            <Row>
                <Col>
                    <input type="text"
                        bind:value={projectFilterTerm}
                        placeholder="Filter"
                        style="width: 100%;">
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
