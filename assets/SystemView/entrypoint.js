import SystemView from './SystemView.svelte';

(async () => {
    new SystemView({
        target: document.getElementById('svelte-app'),
        props: {
            clusterId: clusterInfos.clusterId
        },
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
