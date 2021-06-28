import JobList from './JobList.svelte';

(async () => {
    /* See jobview.js for what clusterCockpitConfigPromise
     * is and where it comes from.
     */
    new JobList({
        target: document.getElementById('svelte-app'),
        props: {
            filterPresets: filterPresets
        },
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
