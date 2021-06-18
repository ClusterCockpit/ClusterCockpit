<script>
    import { Alert } from 'sveltestrap';
    import InfoBox  from './InfoBox.svelte';
    import Tag from './Tag.svelte';
    import { defaultFilters } from './FilterConfig.svelte';

    export let appliedFilters = defaultFilters;
    export let matchedJobs;
    export let clusters;
    export let userInfos = null;

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

    {#if userInfos != null}
        <InfoBox icon="person-circle">
            {userInfos.userId}
        </InfoBox>
        {#if userInfos.name}
            <InfoBox icon="person-lines-fill">
                {userInfos.name}
            </InfoBox>
        {/if}
        {#if userInfos.emal}
            <InfoBox icon="envelope">
                {userInfos.email}
            </InfoBox>
        {/if}
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
                <Tag {tag}/>
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
