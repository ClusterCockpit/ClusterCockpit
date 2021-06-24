<script>
    import { Alert, Icon } from 'sveltestrap';

    export let icon;
</script>

<Alert class="alert-light border d-flex flex-row p-2 me-2" >
    <Icon name={icon}/>
    <div class="ps-2">
        <slot />
    </div>
</Alert>
