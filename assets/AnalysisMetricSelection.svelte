<script>
    import { Modal, ModalBody, ModalHeader, ModalFooter, InputGroup,
             Button, ListGroup, ListGroupItem, Icon } from 'sveltestrap';

    export let availableMetrics;
    export let metricsInHistograms;
    export let metricsInScatterplots;

    let isHistogramConfigOpen = false;
    let isScatterPlotConfigOpen = false;

    let selectedMetric1 = null, selectedMetric2 = null;

    function checkboxChange(event, m1, m2) {
        let checked = event.target.checked;
        if (checked)
            metricsInScatterplots = [...metricsInScatterplots, [m1, m2]];
        else
            metricsInScatterplots = metricsInScatterplots.filter(pair =>
                !(pair[0] == m1 && pair[1] == m2));
    }
</script>

<Button outline
    on:click={() => (isHistogramConfigOpen = true)}>
    <Icon name=""/>
    Select Plots for Histograms
</Button>

<Button outline
    on:click={() => (isScatterPlotConfigOpen = true)}>
    <Icon name=""/>
    Select Plots in Scatter Plots
</Button>

<Modal isOpen={isHistogramConfigOpen}
    toggle={() => (isHistogramConfigOpen = !isHistogramConfigOpen)}>
    <ModalHeader>
        Select metrics presented in histograms
    </ModalHeader>
    <ModalBody>
        <ListGroup>
            {#each availableMetrics as metric (metric)}
                <ListGroupItem>
                    <input type="checkbox" bind:group={metricsInHistograms}
                        value={metric} />

                    {metric}
                </ListGroupItem>
            {/each}
        </ListGroup>
    </ModalBody>
    <ModalFooter>
        <Button color="primary"
            on:click={() => (isHistogramConfigOpen = false)}>
            Close
        </Button>
    </ModalFooter>
</Modal>

<Modal isOpen={isScatterPlotConfigOpen}
    toggle={() => (isScatterPlotConfigOpen = !isScatterPlotConfigOpen)}>
    <ModalHeader>
        Select metric pairs presented in scatter plots
    </ModalHeader>
    <ModalBody>
        <ListGroup>
            {#each metricsInScatterplots as pair}
                <ListGroupItem>
                    <b>{pair[0]}</b> / <b>{pair[1]}</b>

                    <Button style="float: right;" outline color="danger"
                        on:click={() => (
                            metricsInScatterplots = metricsInScatterplots.filter(p => pair != p)
                        )}>
                        <Icon name="x" />
                    </Button>
                </ListGroupItem>
            {/each}
        </ListGroup>

        <br/>

        <InputGroup>
            <select bind:value={selectedMetric1} class="form-group">
                <option value={null}>Choose Metric for X Axis</option>
                {#each availableMetrics as metric}
                    <option value={metric}>{metric}</option>
                {/each}
            </select>
            <select bind:value={selectedMetric2} class="form-group">
                <option value={null}>Choose Metric for Y Axis</option>
                {#each availableMetrics as metric}
                    <option value={metric}>{metric}</option>
                {/each}
            </select>
            <Button outline disabled={selectedMetric1 == null || selectedMetric2 == null}
                on:click={() => {
                    metricsInScatterplots = [...metricsInScatterplots, [selectedMetric1, selectedMetric2]];
                    selectedMetric1 = null;
                    selectedMetric2 = null;
                }}>
                Add Plot
            </Button>
        </InputGroup>

    </ModalBody>
    <ModalFooter>
        <Button color="primary"
            on:click={() => (isScatterPlotConfigOpen = false)}>
            Close
        </Button>
    </ModalFooter>
</Modal>
