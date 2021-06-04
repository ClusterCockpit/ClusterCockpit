<script>
    import { initClient, operationStore, query } from '@urql/svelte';
    import { Col, Row, Table, Card, Spinner } from 'sveltestrap';
    import Histogram from './Histogram.svelte';
    import Datatable from './Datatable.svelte';

    export let userId;

    let selectedCluster = null;
    let selectedMonth = null;
    let selectedYear = null;

    let clusters = [];
    let years = [];
    let months = [
        'January', 'February', 'March', 'April', 'May', 'June', 'July',
        'August', 'September', 'October', 'November', 'December'
    ].map((m, i) => ({ name: m, index: i }));

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`
    });

    const statsQuery = operationStore(`
    query($filter: JobFilterList!) {
       jobsStatistics(filter: $filter) {
           totalJobs
           shortJobs
           totalWalltime
           totalCoreHours
           histWalltime { count, value }
           histNumNodes { count, value }
       }
    }
    `, { filter: { list: [ { userId: { 'eq': userId } } ] } });

    const padNum = (n, len) => n.toString().padStart(len, '0');

    /* TODO/FIXME:
     * Timezone-Stuff!
     * Now that i think about it, timezones could
     * also be ignored in the FilterConfig.
     */
    function updateStatsFilter() {
        let filterItems = [ { userId: { 'eq': userId } } ];

        if (selectedCluster != null)
            filterItems.push({ clusterId: { eq: selectedCluster } });

        if (selectedYear != null) {
            let startDate, endDate;
            // https://stackoverflow.com/questions/222309/calculate-last-day-of-month
            if (selectedMonth != null) {
                startDate = new Date(selectedYear, selectedMonth, 1);
                endDate = new Date(selectedYear, selectedMonth + 1, 0);
            } else {
                startDate = new Date(selectedYear, 0, 1);
                endDate = new Date(selectedYear, 12, 0);
            }

            startDate = `${startDate.getFullYear()}-${padNum(startDate.getMonth() + 1, 2)}-01T00:00:00+00:00`;
            endDate = `${endDate.getFullYear()}-${padNum(endDate.getMonth() + 1, 2)}-${endDate.getDate()}T23:59:59+00:00`;
            filterItems.push({ startTime: { from: startDate, to: endDate } });
        } else {
            selectedMonth = null;
        }

        console.info('stats. filters:', ...filterItems.map(f => Object.entries(f).flat()).flat());
        $statsQuery.variables.filter = { list: filterItems };
    }

    $: updateStatsFilter(selectedCluster, selectedMonth, selectedYear);
    query(statsQuery);

    function onFilterRanges(event) {
        const filterRanges = event.detail;

        const firstJob = new Date(filterRanges.startTime.from);
        const lastJob = new Date(filterRanges.startTime.to);

        years = [];
        for (let year = firstJob.getFullYear(); year <= lastJob.getFullYear(); year++)
            years.push(year);
    }

    let screenWidth = 0;
    let histogramWidth;
    $: histogramWidth = screenWidth / 4 - 10;
</script>

<style>
    h5 {
        text-align: center;
    }
</style>

<Row>
    <Col>
        <div bind:clientWidth={screenWidth} style="width: 100%"><!-- Only for getting the row width --></div>
    </Col>
</Row>
{#if $statsQuery.fetching}
    <div class="d-flex justify-content-center">
        <Spinner secondary />
    </div>
{:else if $statsQuery.error}
    <Card body color="danger" class="mb-3"><h2>Error: {$statsQuery.error.message}</h2></Card>
{:else}
    <Row>
        <Col>
            <h5>
                Show Statistics for:
            </h5>
            <Table>
                <tbody>
                    <tr>
                        <th scope="row">Select Cluster</th>
                        <td>
                            <select bind:value={selectedCluster}>
                                <option value={null}>Any</option>
                                {#each clusters as cluster}
                                    <option value={cluster.clusterID}>
                                        {cluster.clusterID}
                                    </option>
                                {/each}
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Select Year</th>
                        <td>
                            <select bind:value={selectedYear}>
                                <option value={null}>Any</option>
                                {#each years as year}
                                    <option value={year}>
                                        {year}
                                    </option>
                                {/each}
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Select Month</th>
                        <td>
                            <select bind:value={selectedMonth} disabled={selectedYear == null}>
                                <option value={null}>Any</option>
                                {#each months as month}
                                    <option value={month.index}>
                                        {month.name}
                                    </option>
                                {/each}
                            </select>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </Col>
        <Col>
            <Table>
                <tbody>
                    <tr>
                        <th scope="row">User ID</th>
                        <td>{userId}</td>
                    </tr>
                    <tr>
                        <th scope="row">Total Jobs</th>
                        <td>{$statsQuery.data.jobsStatistics.totalJobs}</td>
                    </tr>
                    <tr>
                        <th scope="row">Short Jobs</th>
                        <td>{$statsQuery.data.jobsStatistics.shortJobs}</td>
                    </tr>
                    <tr>
                        <th scope="row">Total Walltime</th>
                        <td>{$statsQuery.data.jobsStatistics.totalWalltime}</td>
                    </tr>
                    <tr>
                        <th scope="row">Total Core Hours</th>
                        <td>{$statsQuery.data.jobsStatistics.totalCoreHours}</td>
                    </tr>
                </tbody>
            </Table>
        </Col>
        <Col>
            <h5>
                Walltime Histogram (In Hours)
            </h5>
            <Histogram width={histogramWidth} height={200}
                data={$statsQuery.data.jobsStatistics.histWalltime} />
        </Col>
        <Col>
            <h5>
                Number of Nodes
            </h5>
            <Histogram width={histogramWidth} height={200}
                data={$statsQuery.data.jobsStatistics.histNumNodes} />
        </Col>
    </Row>
{/if}
<Row>
    <Col>
        <hr/>
    </Col>
</Row>
<Row>
    <Col>
        <Datatable
            restrictToUser={userId}
            on:clusters={({ detail }) => (clusters = detail)}
            on:filter-ranges={onFilterRanges} />
    </Col>
</Row>
