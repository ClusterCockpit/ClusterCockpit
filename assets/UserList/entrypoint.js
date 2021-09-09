import UserList from './UserList.svelte';

(async () => {
    new UserList({
        target: document.getElementById('svelte-app'),
        props: {
            filterPresets: filterPresets
        },
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
