import JobView from './JobView.svelte';

(async () => {
    /* jobInfos has to contains at least the internal ID, the jobId, and the
     * clusterId for the job to be shown. This global variable is set in
     * `templates/jobViews/viewjob.html.twig` when the PHP ClusterCockpit is used.
     *
     * `clusterCockpitConfigPromise` is also declared in that file. It is a promise
     * in order to be flexible and allow for other backends such as cc-jobarchive, where
     * we do not have a template engine to fill in values directly but might need to fetch them first.
     */
    new JobView({
        target: document.getElementById('svelte-app'),
        props: {
            jobInfos: jobInfos
        },
        context: new Map([
            ['cc-config', await clusterCockpitConfigPromise]
        ])
    });
})();
