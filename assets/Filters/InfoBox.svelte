<script>
    import { Alert, Icon } from 'sveltestrap';

    export let icon;
    export let pendingChange = false;
    $: alertType = pendingChange ? 'alert-warning' : 'alert-light';
</script>

<Alert class="{alertType} border d-flex flex-row p-2 me-2" >
    <Icon name={icon}/>
    <div class="ps-2">
        <slot />
    </div>
</Alert>
