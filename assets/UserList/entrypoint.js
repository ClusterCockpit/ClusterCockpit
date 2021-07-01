import UserList from './UserList.svelte';

(async () => {
    new UserList({
        target: document.getElementById('svelte-app'),
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
