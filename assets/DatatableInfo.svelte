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
</script>

<script>
    import { Alert } from 'sveltestrap';
    import InfoBox  from './InfoBox.svelte';
    import { getColorForTag } from './utils.js';

    export let appliedFilters = defaultFilters;
    export let matchedJobs;
    export let clusters;

    function formatDuration({ hours, min }) {
        hours = hours.toString().padStart(2, '0');
        min = min.toString().padStart(2, '0');
        return `${hours}:${min}h`
    }
</script>

<div class="d-flex flex-row mb-2">
    {#if matchedJobs != null}
        <Alert class="p-2 me-2" >
            Matching {matchedJobs} Jobs
        </Alert>
    {/if}

    <InfoBox icon="cpu">
        {appliedFilters["cluster"] == null
        ? (clusters || []).map(c => c.clusterID).join(', ')
        : appliedFilters["cluster"]}
    </InfoBox>

    <InfoBox icon="hdd-stack">
        {appliedFilters["numNodes"]["from"]} - {appliedFilters["numNodes"]["to"]}
    </InfoBox>

    <InfoBox icon="stopwatch">
        {formatDuration(appliedFilters["duration"]["from"])} -
        {formatDuration(appliedFilters["duration"]["to"])}
    </InfoBox>

    <InfoBox icon="calendar-range">
        {appliedFilters["startTime"]["from"]["date"]}
        {appliedFilters["startTime"]["from"]["time"]}
        -
        {appliedFilters["startTime"]["to"]["date"]}
        {appliedFilters["startTime"]["to"]["time"]}
    </InfoBox>

    {#if appliedFilters.projectId}
        <InfoBox icon="people">
            Project ID contains: "{appliedFilters.projectId}"
        </InfoBox>
    {/if}

    {#if Object.values(appliedFilters["tags"]).length > 0}
        <InfoBox icon="tag">
            {#each Object.values(appliedFilters["tags"]) as tag}
                <span class="cc-tag badge rounded-pill {getColorForTag(tag)}">
                    {tag.tagType}: {tag.tagName}
                </span>
            {/each}
        </InfoBox>
    {/if}

    {#if appliedFilters.statistics.some(s => s.enabled)}
        <InfoBox icon="bar-chart-line">
            {#each appliedFilters.statistics.filter(s => s.enabled) as stat}
                {stat.name}: {stat.from} - {stat.to}
            {/each}
        </InfoBox>
    {/if}
</div>
