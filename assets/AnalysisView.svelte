<script>
    import { setContext, getContext } from 'svelte';
    import { initClient, operationStore, query } from '@urql/svelte';

    const clusterCockpitConfig = getContext('cc-config');

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`
    });
</script>

<style>

</style>

<h1>Hello World!</h1>