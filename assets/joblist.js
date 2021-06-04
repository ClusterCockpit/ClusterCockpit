import Datatable from './Datatable.svelte';

(async () => {
    /* See jobview.js for what clusterCockpitConfigPromise
     * is and where it comes from.
     */
    new Datatable({
        target: document.getElementById('svelte-app'),
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
