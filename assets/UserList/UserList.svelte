<script>
    import { initClient, operationStore, query, getClient } from '@urql/svelte';
    import { Table, Card, Spinner, Icon, Button, Row, Col } from 'sveltestrap';

    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`
    });

    let startTime = null;
    let stopTime = null;
    let clusterId = null;

    const lastMonth = new Date();
    lastMonth.setMonth(lastMonth.getMonth() - 1);

    let rawStartTime = lastMonth.toISOString().split('T')[0];
    let rawStopTime = (new Date()).toISOString().split('T')[0];

    let sorting = { field: 'totalJobs', direction: 'down' };

    function changeSorting(event, field) {
        let target = event.target;
        while (target.tagName != 'BUTTON')
            target = target.parentElement;

        let direction = target.children[0].className.includes('up') ? 'down' : 'up';
        target.children[0].className = `bi-sort-numeric-${direction}`;

        sorting = { field, direction };
    }

    function sortUsers(users, sorting) {
        let cmp = sorting.field == 'userId'
            ? (sorting.direction == 'up'
                ? (a, b) => a.userId < b.userId
                : (a, b) => a.userId > b.userId)
            : (sorting.direction == 'up'
                ? (a, b) => a[sorting.field] - b[sorting.field]
                : (a, b) => b[sorting.field] - a[sorting.field]);

        users.sort(cmp);
        return users;
    }

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

    $: $usersQuery.variables.clusterId = clusterId;

    let clusters = [];
    getClient()
        .query(`query {
            clusters { clusterID }
        }`)
        .toPromise()
        .then((res) => {
            if (res.error) {
                console.error(res.error);
                return;
            }

            clusters = res.data.clusters.map(c => c.clusterID);
        });


    function dateSelected() {
        if (!rawStartTime && !rawStopTime)
            return;

        startTime = new Date(rawStartTime || 0);
        stopTime = new Date(rawStopTime || Date.now());

        const padNum = (n, len = 2) => n.toString().padStart(len, '0');
        startTime = `${startTime.getFullYear()}-${padNum(startTime.getMonth() + 1)}-01T00:00:00+00:00`;
        stopTime = `${stopTime.getFullYear()}-${padNum(stopTime.getMonth() + 1)}-${padNum(stopTime.getDate() + 1)}T23:59:59+00:00`;

        $usersQuery.variables.startTime = startTime;
        $usersQuery.variables.stopTime = stopTime;
    }

    $: dateSelected(rawStartTime, rawStopTime);

    query(usersQuery);
</script>

<style>
    th[scope="col"] > :global(button) {
        float: right;
    }
    input, select {
        margin-bottom: 0px;
    }
</style>

<Row>
    <Col style="display: flex; align-items: center;">
        Filters on jobs in the statistics:
    </Col>
    <Col>
        Cluster:
        <select bind:value={clusterId}>
            <option value={null}>Any</option>
            {#each clusters as cluster}
                <option value={cluster}>{cluster}</option>
            {/each}
        </select>
    </Col>
    <Col>
        Start Time:
        From
        <input type="date" bind:value={rawStartTime} />
        to
        <input type="date" bind:value={rawStopTime} />
    </Col>
</Row>
<Table>
    <thead>
        <tr>
            <th scope="col">
                Username
                <Button color="{sorting.field == 'userId' ? 'primary' : 'light'}"
                    size="sm" on:click={e => changeSorting(e, 'userId')}>
                    <Icon name="sort-numeric-down" />
                </Button>
            </th>
            <th scope="col">
                Total Jobs
                <Button color="{sorting.field == 'totalJobs' ? 'primary' : 'light'}"
                    size="sm" on:click={e => changeSorting(e, 'totalJobs')}>
                    <Icon name="sort-numeric-down" />
                </Button>
            </th>
            <th scope="col">
                Total Walltime
                <Button color="{sorting.field == 'totalWalltime' ? 'primary' : 'light'}"
                    size="sm" on:click={e => changeSorting(e, 'totalWalltime')}>
                    <Icon name="sort-numeric-down" />
                </Button>
            </th>
            <th scope="col">
                Total Core Hours
                <Button color="{sorting.field == 'totalCoreHours' ? 'primary' : 'light'}"
                    size="sm" on:click={e => changeSorting(e, 'totalCoreHours')}>
                    <Icon name="sort-numeric-down" />
                </Button>
            </th>
        </tr>
    </thead>
    <tbody>
        {#if $usersQuery.fetching}
            <tr>
                <td colspan="4" style="text-align: center;">
                    <Spinner secondary />
                </td>
            </tr>
        {:else if $usersQuery.error}
            <tr>
                <td colspan="4">
                    <Card body color="danger" class="mb-3">Error: {$usersQuery.error.message}</Card>
                </td>
            </tr>
        {:else}
            {#each sortUsers($usersQuery.data.userStats, sorting) as user (user.userId)}
                <tr>
                    <td>
                        <a href="/monitoring/user/{user.id}" target="_blank">
                            {user.userId}
                        </a>
                    </td>
                    <td>{user.totalJobs}</td>
                    <td>{user.totalWalltime.toFixed(2)}</td>
                    <td>{user.totalCoreHours.toFixed(2)}</td>
                </tr>
            {/each}
        {/if}
    </tbody>
</Table>
