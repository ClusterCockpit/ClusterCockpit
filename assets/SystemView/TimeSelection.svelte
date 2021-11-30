<script>
    import { Icon, Input, InputGroup, InputGroupText } from 'sveltestrap';
    import { createEventDispatcher } from "svelte";

    export let from;
    export let to;

    const dispatch = createEventDispatcher();
    let timeRange = (to.getTime() - from.getTime()) / 1000;

    function updateTimeRange(event) {
        if (timeRange == -1) {
            from = null;
            to = null;
            return;
        }

        let now = Date.now(), t = timeRange * 1000;
        from = new Date(now - t);
        to = new Date(now);
        dispatch('change', { from, to });
    }

    function updateExplicitTimeRange(type, event) {
        let d = new Date(Date.parse(event.target.value));
        if (type == 'from') from = d;
        else                to = d;

        if (from != null && to != null)
            dispatch('change', { from, to });
    }
</script>

<InputGroup>
    <InputGroupText><Icon name="clock-history"/></InputGroupText>
    <InputGroupText>
        Time
    </InputGroupText>
    <select class="form-select" bind:value={timeRange} on:change={updateTimeRange}>
        <option value={-1}>Custom</option>
        <option value={30 * 60}>Last half hour</option>
        <option value={60 * 60}>Last hour</option>
        <option value={2 * 60 * 60}>Last 2hrs</option>
        <option value={4 * 60 * 60}>Last 4hrs</option>
        <option value={24 * 60 * 60}>Last day</option>
        <option value={7 * 24 * 60 * 60}>Last week</option>
    </select>
    {#if timeRange == -1}
        <InputGroupText>from</InputGroupText>
        <Input type="datetime-local" on:change={(event) => updateExplicitTimeRange('from', event)}></Input>
        <InputGroupText>to</InputGroupText>
        <Input type="datetime-local" on:change={(event) => updateExplicitTimeRange('to', event)}></Input>
    {/if}
</InputGroup>
