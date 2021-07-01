<script>
    import { getContext } from 'svelte';
    import { Table, Icon, Button } from 'sveltestrap';
    import ColumnConfig from '../Common/ColumnConfig.svelte';

    export let job;
    export let jobMetrics;

    const clusterCockpitConfig = getContext('cc-config');
    const metricConfig = getContext('metric-config')[job.clusterId];

    let nodes = jobMetrics[0].metric.series.map(s => s.node_id);
    let columnConfigOpen = false;
    let currentSorting = null;
    let selectedMetrics = clusterCockpitConfig.job_view_nodestats_selectedMetrics
        ? clusterCockpitConfig['job_view_nodestats_selectedMetrics']
        : ['flops_any', 'mem_bw', 'mem_used'];

    function getStats(metric, nodeId) {
        let data = jobMetrics.find(m => m.name === metric);
        if (data == null)
            return null;

        let series = data.metric.series
            .find(s => s.node_id === nodeId);

        console.assert(series != null);
        return series.statistics;
    }

    function changeNodeSorting(metric, stat, event) {
        let target = event.target;
        // The event target can be the button or the icon.
        while (target.tagName != 'BUTTON')
            target = target.parentElement;

        let dir = target.children[0].className.includes('up') ? 'down' : 'up';
        target.children[0].className = `bi-sort-numeric-${dir}`;
        currentSorting = metric+'|'+stat;

        let data = jobMetrics.find(m => m.name === metric);
        if (data == null)
            return;

        nodes = nodes.sort((nodeA, nodeB) => {
            let statA = data.metric.series.find(s => s.node_id == nodeA).statistics[stat];
            let statB = data.metric.series.find(s => s.node_id == nodeB).statistics[stat];
            return dir == 'up' ? statB - statA : statA - statB;
        });
    }

</script>

<style media="screen">
    td, th {
        text-align: center;
    }
</style>

<ColumnConfig
    configName="job_view_nodestats_selectedMetrics"
    bind:isOpen={columnConfigOpen}
    bind:selectedMetrics={selectedMetrics} />

<Table>
    <thead>
        <tr>
            <th>
                <Button outline
                    on:click={() => (columnConfigOpen = !columnConfigOpen)}>
                    <Icon name="gear" />
                </Button>
            </th>
            {#each selectedMetrics as metric}
                <th scope="col" colspan="3">
                    {metric} [{metricConfig[metric].unit}]
                </th>
            {/each}
        </tr>
        <tr>
            <th>Nodes</th>
            {#each selectedMetrics as metric (metric)}
                {#each ['min', 'max', 'avg'] as stat}
                    <th scope="col">
                        {stat}
                        <Button color="{currentSorting == metric+'|'+stat ? 'primary' : 'light'}"
                            size="sm" on:click={e => changeNodeSorting(metric, stat, e)}>
                            <Icon name="sort-numeric-up" />
                        </Button>
                    </th>
                {/each}
            {/each}
        </tr>
    </thead>
    <tbody>
        {#each nodes as nodeId}
            <tr>
                <th scope="row">{nodeId}</th>
                {#each selectedMetrics.map(metric => getStats(metric, nodeId)) as stats}
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
</Table>
