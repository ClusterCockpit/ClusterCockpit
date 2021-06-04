import UserView from './UserView.svelte';

(async () => {
    new UserView({
        target: document.getElementById('svelte-app'),
        props: {
            userId: userId
        },
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
