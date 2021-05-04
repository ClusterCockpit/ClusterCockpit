<script>
    import  { Modal, ModalBody, ModalHeader,
              ModalFooter, Button, ListGroup } from 'sveltestrap';
    import { getContext } from 'svelte';

    export let metrics;
    export let selectedMetrics;
    export let isOpen;

    const metricConfig = getContext('metric-config');
    let newMetricsOrder;
    let unorderedSelectedMetrics;
    let columnHovering;

    function selectedMetricsChanged() {
        newMetricsOrder = [...metrics];
        unorderedSelectedMetrics = [...selectedMetrics];
    }

    function columnsDragStart(event, i) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.dropEffect = 'move';
        event.dataTransfer.setData('text/plain', i);
    }

    function columnsDrag(event, target) {
        event.dataTransfer.dropEffect = 'move';
        const start = Number.parseInt(event.dataTransfer.getData("text/plain"));
        if (start < target) {
            newMetricsOrder.splice(target + 1, 0, newMetricsOrder[start]);
            newMetricsOrder.splice(start, 1);
        } else {
            newMetricsOrder.splice(target, 0, newMetricsOrder[start]);
            newMetricsOrder.splice(start + 1, 1);
        }
        columnHovering = null;
    }

    function closeAndApply() {
        metrics = [...newMetricsOrder];
        selectedMetrics = metrics.filter(m =>
            unorderedSelectedMetrics.includes(m));
        isOpen = false;
    }

    $: selectedMetricsChanged(selectedMetrics);
</script>

<style>
    li.cc-config-column {
        display: block;
        cursor: grab;
    }

    li.cc-config-column.is-active {
        background-color: #3273dc;
        color: #fff;
        cursor: grabbing;
    }
</style>

<Modal isOpen={isOpen} toggle={() => (isOpen = !isOpen)}>
    <ModalHeader>
        Configure columns
    </ModalHeader>
    <ModalBody>
        <ListGroup>
            {#each newMetricsOrder as metric, index (metric)}
                <li class="cc-config-column list-group-item"
                    draggable={true} ondragover="return false"
                    on:dragstart={event => columnsDragStart(event, index)}
                    on:drop|preventDefault={event => columnsDrag(event, index)}
                    on:dragenter={() => columnHovering = index}
                    class:is-active={columnHovering === index}>
                    {#if unorderedSelectedMetrics.includes(metric)}
                        <input type="checkbox" bind:group={unorderedSelectedMetrics} value={metric} checked>
                    {:else}
                        <input type="checkbox" bind:group={unorderedSelectedMetrics} value={metric}>
                    {/if}
                    {metric}
                    <span style="float: right;">
                        {Object.keys(metricConfig).filter(c => metricConfig[c][metric] != null).join(', ')}
                    </span>
                </li>
            {/each}
        </ListGroup>
    </ModalBody>
    <ModalFooter>
        <Button color="primary" on:click={closeAndApply}>Close & Apply</Button>
    </ModalFooter>
</Modal>
