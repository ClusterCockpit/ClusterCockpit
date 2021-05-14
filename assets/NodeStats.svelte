<script>
    import { getContext } from 'svelte';
    import { Table, Icon, Button,
             ListGroup, ListGroupItem,
             Modal, ModalBody, ModalHeader, ModalFooter } from 'sveltestrap';

    export let job;
    export let jobMetrics;

    const metricConfig = getContext('metric-config')[job.clusterId];
    const metrics = Object.keys(metricConfig);

    let nodes = jobMetrics[0].metric.series.map(s => s.node_id);
    let selectedMetrics = [...metrics];
    let columnConfigOpen = false;
    let currentSorting = null;

    function getStats(metric, nodeId) {
        let data = jobMetrics.find(m => m.name === metric);
        if (data == null)
            return null;

        let series = data.metric.series
            .find(s => s.node_id === nodeId);

        console.assert(series != null);
        return series.statistics;
    }

    function toggleColumnConfig() {
        columnConfigOpen = !columnConfigOpen;
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
    td {
        text-align: center;
    }
</style>

<Modal isOpen={columnConfigOpen} toggle={toggleColumnConfig}>
    <ModalHeader>
        Select Metrics
    </ModalHeader>
    <ModalBody>
        <ListGroup>
            {#each metrics as metric}
                <ListGroupItem>
                    <input type="checkbox" bind:group={selectedMetrics} value="{metric}"/>
                    {metric}
                </ListGroupItem>
            {/each}
        </ListGroup>
    </ModalBody>
    <ModalFooter>
        <Button color="primary" on:click={toggleColumnConfig}>Close</Button>
    </ModalFooter>
</Modal>

<Table>
    <thead>
        <tr>
            <th>
                <Button outline on:click={toggleColumnConfig}><Icon name="gear" /></Button>
            </th>
            {#each selectedMetrics as metric}
                <th scope="col" colspan="3">
                    {metric} [{metricConfig[metric].unit}]
                </th>
            {/each}
        </tr>
        <tr>
            <th>Nodes</th>
            {#each selectedMetrics as metric}
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
        {#each nodes as nodeId (nodeId)}
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
