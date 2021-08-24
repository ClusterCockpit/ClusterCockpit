<script>
    import { Alert, Spinner } from 'sveltestrap';
    import InfoBox  from './InfoBox.svelte';
    import Tag from '../Common/Tag.svelte';
    import { defaultFilters } from './Filters.svelte';
    import { getContext } from 'svelte';
    import { arraysEqual } from '../Common/utils';

    export let pendingFilters = defaultFilters;
    export let appliedFilters = defaultFilters;
    export let matchedJobs;
    export let userInfos = null;

    const clustersQuery = getContext('clusters-query');

    function formatDuration({ hours, min }) {
        hours = hours.toString().padStart(2, '0');
        min = min.toString().padStart(2, '0');
        return `${hours}:${min}h`
    }

    const isRangeEqual = (a, b) => a.from == b.from && a.to == b.to;
    const isDurationRangeEqual = (a, b) => a.from.hours == b.from.hours
        && a.to.hours == b.to.hours
        && a.from.min == b.from.min
        && a.to.min == b.to.min;
    const isCalenderRangeEqual = (a, b) => a.from.date == b.from.date
        && a.to.date == b.to.date
        && a.from.time == b.from.time
        && a.to.time == b.to.time;

    $: tagsFilterPending = !arraysEqual(Object.keys(appliedFilters.tags), Object.keys(pendingFilters.tags));
    $: statsFilterPending = pendingFilters.statistics.some((s, idx) => !isRangeEqual(s, appliedFilters.statistics[idx]));
</script>

<div class="d-flex flex-row mb-2">
{#if $clustersQuery.fetching}
    <Alert color="light" class="p-2 me-2" fade={false}>
        <Spinner secondary/>
    </Alert>
{:else if $clustersQuery.error}
    <Alert color="danger" class="p-2 me-2" fade={false}>
        {$clustersQuery.error}
    </Alert>
{:else}
    {#if matchedJobs != null}
        <Alert class="p-2 me-2" fade={false}>
            Matching {matchedJobs} Jobs
        </Alert>
    {/if}

    {#if userInfos != null}
        <InfoBox icon="person-circle">
            {userInfos.userId}
            {#if userInfos.name}
                ({userInfos.name})
            {/if}
            {#if userInfos.email}
                , <a href="mailto:{userInfos.email}">{userInfos.email}</a>
            {/if}
        </InfoBox>
    {/if}

    <InfoBox icon="cpu"
        pendingChange={appliedFilters.cluster != pendingFilters.cluster}>
        {appliedFilters.cluster == null
        ? ($clustersQuery.clusters || []).map(c => c.clusterID).join(', ')
        : appliedFilters.cluster}
    </InfoBox>

    <InfoBox icon="hdd-stack"
        pendingChange={!isRangeEqual(appliedFilters.numNodes, pendingFilters.numNodes)}>
        {appliedFilters.numNodes.from} - {appliedFilters.numNodes.to}
    </InfoBox>

    <InfoBox icon="stopwatch"
        pendingChange={!isDurationRangeEqual(appliedFilters.duration, pendingFilters.duration)}>
        {formatDuration(appliedFilters.duration.from)} -
        {formatDuration(appliedFilters.duration.to)}
    </InfoBox>

    <InfoBox icon="calendar-range"
        pendingChange={!isCalenderRangeEqual(appliedFilters.startTime, pendingFilters.startTime)}>
        {appliedFilters.startTime.from.date}
        {appliedFilters.startTime.from.time}
        -
        {appliedFilters.startTime.to.date}
        {appliedFilters.startTime.to.time}
    </InfoBox>

    {#if appliedFilters.isRunning != null || appliedFilters.isRunning != pendingFilters.isRunning}
        <InfoBox icon="gear" pendingChange={appliedFilters.isRunning != pendingFilters.isRunning}>
            { appliedFilters.isRunning == null
                ? "Running or finished jobs"
                : (appliedFilters.isRunning ? "Running jobs" : "Finished jobs") }
        </InfoBox>
    {/if}

    {#if appliedFilters.projectId || appliedFilters.projectId != pendingFilters.projectId}
        <InfoBox icon="people" pendingChange={appliedFilters.projectId != pendingFilters.projectId}>
            Project ID contains: "{appliedFilters.projectId}"
        </InfoBox>
    {/if}

    {#if Object.values(appliedFilters.tags).length > 0 || tagsFilterPending}
        <InfoBox icon="tag" pendingChange={tagsFilterPending}>
            {#each Object.values(appliedFilters.tags) as tag}
                <Tag {tag}/>
            {/each}
        </InfoBox>
    {/if}

    {#if appliedFilters.statistics.some(s => s.changed) || statsFilterPending}
        <InfoBox icon="bar-chart-line" pendingChange={statsFilterPending}>
            {appliedFilters.statistics.filter(s => s.changed).map(stat =>
                `${stat.name}: ${stat.from} - ${stat.to}`).join(', ')}
        </InfoBox>
    {/if}
{/if}
</div>
