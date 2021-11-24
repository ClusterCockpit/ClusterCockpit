<script>
    import Tag from '../Common/Tag.svelte';
    import { Badge } from 'sveltestrap';

    export let job;

    function formatDuration(duration) {
        const hours = Math.floor(duration / 3600);
        duration -= hours * 3600;
        const minutes = Math.floor(duration / 60);
        duration -= minutes * 60;
        const seconds = duration;
        return `${hours}:${('0' + minutes).slice(-2)}:${('0' + seconds).slice(-2)}`;
    }

    const getJobUrl = typeof JOBVIEW_URL !== 'undefined'
        ? JOBVIEW_URL
        : job => `/monitoring/job/${job.id}`;

    const getUserUrl = typeof USERVIEW_URL !== 'undefined'
        ? USERVIEW_URL
        : userId => `/monitoring/user/${userId}`;
</script>

<div>
    <div class="fw-bold">
        <a href="{getJobUrl(job)}" target="_blank">{job["jobId"]}</a>
        ({job["clusterId"]})
    </div>
    <div class="fst-italic">
		<a href="{getUserUrl(job.userId)}" target="_blank">{job["userId"]}</a>
        {#if job["projectId"] && job["projectId"] != 'no project'}
            ({job["projectId"]})
        {/if}
    </div>
    <p>{job["numNodes"]} nodes</p>
    <div>Started at:</div>
    <p class="fw-bold">{job["startTime"]}</p>
    <div>Duration:</div>
    <p class="fw-bold">
        {formatDuration(job["duration"])}
        {#if job.state == 'running'}
            <Badge color="success">Running</Badge>
        {:else if job.state != 'completed'}
            <Badge color="danger">{job.state}</Badge>
        {/if}
    </p>
    <p>
        {#each job["tags"] as tag}
            <Tag tag={tag}/>
        {/each}
    </p>
</div>
