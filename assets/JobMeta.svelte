<script>
    import { getColorForTag } from './utils.js';

    export let job;

    function formatDuration(duration) {
        const hours = Math.floor(duration / 3600);
        duration -= hours * 3600;
        const minutes = Math.floor(duration / 60);
        duration -= minutes * 60;
        const seconds = duration;
        return `${hours}:${('0' + minutes).slice(-2)}:${('0' + seconds).slice(-2)}`;
    }
</script>

<div>
    <div class="fw-bold">
        <a href="/monitoring/job/{job["id"]}">
            {job["jobId"]} ({job["clusterId"]})
        </a>
    </div>
    <div class="fst-italic">
        {job["userId"]}
        {#if job["projectId"] && job["projectId"] != 'no project'}
            ({job["projectId"]})
        {/if}
    </div>
    <p>{job["numNodes"]} nodes</p>
    <div>Started at:</div>
    <p class="fw-bold">{job["startTime"]}</p>
    <div>Duration:</div>
    <p class="fw-bold">{formatDuration(job["duration"])}</p>
    <p>
        {#each job["tags"] as tag}
            <span class="badge rounded-pill {getColorForTag(tag)}">
                {tag.tagType}: {tag.tagName}
            </span>
        {/each}
    </p>
</div>
