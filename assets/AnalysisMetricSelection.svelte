<script>
    import { Modal, ModalBody, ModalHeader, ModalFooter, Table,
             Button, ListGroup, ListGroupItem, Icon } from 'sveltestrap';
    import InfoBox from './InfoBox.svelte';

    export let availableMetrics;
    export let metricsInHistograms;
    export let metricsInScatterplots;

    let isOpen = false;

    function checkboxChange(event, m1, m2) {
        let checked = event.target.checked;
        if (checked)
            metricsInScatterplots = [...metricsInScatterplots, [m1, m2]];
        else
            metricsInScatterplots = metricsInScatterplots.filter(pair =>
                !(pair[0] == m1 && pair[1] == m2));
    }
</script>

<Button  outline on:click={() => (isOpen = !isOpen)}>
    <Icon name="speedometer" />
    Select Metrics
</Button>

<div class="d-flex flex-row mb-2">
    {#if metricsInHistograms.length > 0}
        <InfoBox icon="bar-chart">
            Histograms:
            {@html metricsInHistograms.map(m => `<b>${m}</b>`).join(', ')}
        </InfoBox>
    {/if}

    {#if metricsInScatterplots.length > 0}
        <InfoBox icon="graph-up">
            Scatter Plots:
            {@html metricsInScatterplots.map(([m1, m2]) =>
                `<b>${m1}</b> / <b>${m2}</b>`).join(', ')}
        </InfoBox>
    {/if}
</div>

<Modal {isOpen} toggle={() => (isOpen = !isOpen)}>
    <ModalHeader>
        Select Metrics
    </ModalHeader>
    <ModalBody>
        <h5>For Histograms:</h5>
        <ListGroup>
            {#each availableMetrics as metric (metric)}
                <ListGroupItem>
                    <input type="checkbox" bind:group={metricsInHistograms}
                        value={metric} />

                    {metric}
                </ListGroupItem>
            {/each}
        </ListGroup>
        <br/>
        <h5>For Scatter Plots</h5>
        <ListGroup>
            <div style="overflow-x: scroll;">
            <Table>
                <thead>
                    <tr>
                        <th><!-- ... --></th>
                        {#each availableMetrics as metric}
                            <th scope="col">{metric}</th>
                        {/each}
                    </tr>
                </thead>
                <tbody>
                    {#each availableMetrics as m1}
                        <tr>
                            <th scope="row">{m1}</th>
                            {#each availableMetrics as m2}
                                <td style="text-align: center;">
                                {#if m1 != m2}
                                    <input type="checkbox"
                                        checked={metricsInScatterplots.find(pair =>
                                            m1 == pair[0] && m2 == pair[1]) != null}
                                        on:change={(e) => checkboxChange(e, m1, m2)} />
                                {/if}
                                </td>
                            {/each}
                        </tr>
                    {/each}
                </tbody>
            </Table>
            </div>
        </ListGroup>
    </ModalBody>
    <ModalFooter>
        <Button color="primary" on:click={() => (isOpen = false)}>Close</Button>
    </ModalFooter>
</Modal>
