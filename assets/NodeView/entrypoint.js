import NodeView from './NodeView.svelte';

(async () => {
    new NodeView({
        target: document.getElementById('svelte-app'),
        props: {
            nodeId: nodeInfos.nodeId,
            clusterId: nodeInfos.clusterId
        },
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
