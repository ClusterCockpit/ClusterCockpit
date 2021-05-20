import JobView from './JobView.svelte';

const jobView = new JobView({
    target: document.getElementById('svelte-app'),
    props: {
        /* Originally set in templates/jobViews/viewJob-svelte.html.twig */
        jobInfos: jobInfos
    }
});
