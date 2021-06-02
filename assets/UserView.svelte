<script>
    import { initClient, operationStore, query } from '@urql/svelte';
    import { Col, Row, Table, Card, Spinner } from 'sveltestrap';
    import Histogram from './Histogram.svelte';
    import Datatable from './Datatable.svelte';

    export let userId;

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

    query(statsQuery);

    let screenWidth = 0;
    let histogramWidth;
    $: histogramWidth = screenWidth / 3 - 10;
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
                #Nodes Histogram
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
        <Datatable restrictToUser={userId} />
    </Col>
</Row>
