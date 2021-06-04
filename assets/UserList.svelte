<script>
    import { initClient, operationStore, query } from '@urql/svelte';
    import { Col, Row, Table, Card, Spinner } from 'sveltestrap';

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`
    });

    let startTime = null;
    let stopTime = null;
    let clusterId = null;

    const usersQuery = operationStore(`
    query($startTime: Time, $stopTime: Time, $clusterId: String) {
       userStats(startTime: $startTime, stopTime: $stopTime, clusterId: $clusterId) {
           id,
           userId
           totalJobs
           totalWalltime
           totalCoreHours
       }
    }
    `, { startTime, stopTime, clusterId });

    query(usersQuery);

</script>

{#if $usersQuery.fetching}
    <div class="d-flex justify-content-center">
        <Spinner secondary />
    </div>
{:else if $usersQuery.error}
    <Card body color="danger" class="mb-3"><h2>Error: {$usersQuery.error.message}</h2></Card>
{:else}
    <Table>
        <thead>
            <tr>
                <th scope="col">Username</th>
                <th scope="col">Total Jobs</th>
                <th scope="col">Total Walltime</th>
                <th scope="col">Total Core Hours</th>
            </tr>
        </thead>
        <tbody>
            {#each $usersQuery.data.userStats as user (user.userId)}
                <tr>
                    <td>{user.userId}</td>
                    <td>{user.totalJobs}</td>
                    <td>{user.totalWalltime.toFixed(2)}</td>
                    <td>{user.totalCoreHours.toFixed(2)}</td>
                </tr>
            {/each}
        </tbody>
    </Table>
{/if}
