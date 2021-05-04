<script>
    import { Card, Spinner } from 'sveltestrap';
    import { operationStore, query, getClient } from '@urql/svelte';
    import Plot from './Plot.svelte';

    export let jobId;
    export let clusterId;
    export let width;
    export let height;
    export let selectedMetrics;

    const rawQuery = `
        query($jobId: String!, $clusterId: String, $metrics: [String]!) {
            jobMetrics(
                jobId: $jobId,
                clusterId: $clusterId,
                metrics: $metrics
            ) {
                name,
                metric {
                    unit,
                    scope,
                    timestep,
                    series {
                        node_id,
                        statistics {
                            avg, min, max
                        },
                        data
                    }
                }
            }
        }
    `;

    const jobDataQuery = operationStore(rawQuery, {
        jobId, clusterId,
        metrics: selectedMetrics
    });

    function sortQueryData(data) {
        const obj = data.reduce((obj, e) => {
            obj[e['name']] = e;
            return obj;
        }, {});

        return selectedMetrics.map((name) => ({
            name,
            data: obj[name]?.metric,
            loading: obj[name]?.loading,
            error: obj[name]?.error
        }));
    }

    let triggerUpdate = 0;
    let oldSelectedMetrics = selectedMetrics.slice();
    let oldQueryData = null;

    function prepareData(initialQueryData) {
        /* The jobId changed:  */
        if (oldSelectedMetrics == null) {
            oldSelectedMetrics = selectedMetrics.slice();
            return sortQueryData($jobDataQuery.data.jobMetrics);
        }

        if (oldQueryData == null)
            oldQueryData = initialQueryData;

        let data = [...oldQueryData];

        selectedMetrics
            .filter(metric => !oldSelectedMetrics.includes(metric))
            .map(metric => {
                getClient()
                    .query(rawQuery, { jobId, clusterId, metrics: [metric] })
                    .toPromise()
                    .then(res => {
                        if (res.error || res.data.jobMetrics.length != 1) {
                            oldQueryData.push({
                                name: metric,
                                error: res.error
                            });
                            triggerUpdate += 1;
                            return;
                        }

                        oldQueryData
                            .filter(e => e.name == metric)
                            .forEach(e => {
                                e.loading = false;
                                e.metric = res.data.jobMetrics[0].metric;
                            });

                        triggerUpdate += 1;
                    });

                data.push({
                    name: metric,
                    loading: true
                });
            });

        oldSelectedMetrics = selectedMetrics;
        oldQueryData = data;
        return sortQueryData(data);
    }

    function updateQuery() {
        $jobDataQuery.variables.jobId = jobId;
        $jobDataQuery.variables.clusterId = clusterId;
        oldSelectedMetrics = null;
        oldQueryData = null;
    }

    $: updateQuery(jobId, clusterId);

    query(jobDataQuery);
</script>

{#if $jobDataQuery.fetching}
    <td colspan="{selectedMetrics.length}">
        <Spinner secondary />
    </td>
{:else if $jobDataQuery.error}
    <td colspan="{selectedMetrics.length}">
        <Card body color="danger" class="mb-3">Error: {$jobDataQuery.error.message}</Card>
    </td>
{:else}
    {#each prepareData($jobDataQuery.data.jobMetrics, selectedMetrics, triggerUpdate) as metric (metric.name)}
        <td class="cc-plot-{jobId.replace('.', '_')}-{metric.name}">
            {#if metric.data}
                {#key metric.data}
                    <Plot
                        metric={metric.name}
                        clusterId={clusterId}
                        data={metric.data}
                        height={height}
                        width={width / selectedMetrics.length}/>
                {/key}
            {:else if metric.error}
                <Card body color="danger">{metric.error.message}</Card>
            {:else if metric.loading}
                <Spinner secondary />
            {:else}
                <Card body color="warning">Missing Data</Card>
            {/if}
        </td>
    {/each}
{/if}
