import UserView from './UserView.svelte';

(async () => {
    new UserView({
        target: document.getElementById('svelte-app'),
        props: {
            userId: "mpt2006h" /* FIXME: Replace Me! */
        },
        context: [
            ['cc-config', await clusterCockpitConfigPromise]
        ]
    });
})();
