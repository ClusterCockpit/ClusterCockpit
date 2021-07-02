import UserView from './UserView.svelte';

(async () => {
    new UserView({
        target: document.getElementById('svelte-app'),
        props: {
            userInfos: userInfos
        },
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
