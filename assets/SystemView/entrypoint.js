import SystemView from './SystemView.svelte';

(async () => {
    new SystemView({
        target: document.getElementById('svelte-app'),
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
