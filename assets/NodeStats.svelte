<script>
    export let job;
    export let jobMetrics;

    import { getContext } from 'svelte';

    const metricConfig = getContext('metric-config')[job.clusterId];
    const metrics = Object.keys(metricConfig);
    const nodes = jobMetrics[0].metric.series.map(s => s.node_id);

    function getStats(metric, nodeId) {
        let data = jobMetrics.find(m => m.name === metric);
        if (data == null)
            return null;

        let series = data.metric.series
            .find(s => s.node_id === nodeId);

        console.assert(series != null);
        return series.statistics;
    }
</script>

<style media="screen">
    td {
        text-align: center;
    }
</style>

<table class="table">
    <thead>
        <tr>
            <th></th>
            {#each metrics as metric}
                <th scope="col" colspan="3">
                    {metric} [{metricConfig[metric].unit}]
                </th>
            {/each}
        </tr>
        <tr>
            <th>Nodes</th>
            {#each metrics as metric}
                <th scope="col">
                    Min.
                </th>
                <th scope="col">
                    Max.
                </th>
                <th scope="col">
                    Avg.
                </th>
            {/each}
        </tr>
    </thead>
    <tbody>
        {#each nodes as nodeId}
            <tr>
                <th scope="row">{nodeId}</th>
                {#each metrics.map(metric => getStats(metric, nodeId)) as stats}
                    {#if stats}
                        <td>{stats.min}</td>
                        <td>{stats.max}</td>
                        <td>{stats.avg}</td>
                    {:else}
                        <td colspan="3">
                            <i>No Data</i>
                        </td>
                    {/if}
                {/each}
            </tr>
        {/each}
    </tbody>
</table>
