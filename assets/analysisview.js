import AnalysisView from './AnalysisView.svelte';

(async () => {
    new AnalysisView({
        target: document.getElementById('svelte-app'),
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
