<script>
    import { initGraphQL } from '../Common/gqlclient.js';
    import { getContext } from 'svelte';

    initGraphQL(getContext('cc-config'));

    import { operationStore, query, getClient } from '@urql/svelte';
    import { Table, Card, Spinner, Icon, Button, Row, Col, Alert,
             InputGroup, InputGroupText } from 'sveltestrap';

    export let filterPresets = null;

    let startTime = null, stopTime = null;
    let rawStartTime = null, rawStopTime = null;
    let clusterId = null;
    let hideUsersWithNoJobs = false;
    let usernameFilter = '';

    if (filterPresets && filterPresets.startTime) {
        startTime = new Date(filterPresets.startTime.from);
        stopTime = new Date(filterPresets.startTime.to);
        const pad = (n) => n.toString().padStart(2, '0');
        rawStartTime = `${startTime.getFullYear()}-${pad(startTime.getMonth() + 1)}-${pad(startTime.getDate())}`;
        rawStopTime = `${stopTime.getFullYear()}-${pad(stopTime.getMonth() + 1)}-${pad(stopTime.getDate())}`;
    }

    let sorting = { field: 'totalJobs', direction: 'down' };

    function changeSorting(event, field) {
        let target = event.target;
        while (target.tagName != 'BUTTON')
            target = target.parentElement;

        let direction = target.children[0].className.includes('up') ? 'down' : 'up';
        target.children[0].className = `bi-sort-numeric-${direction}`;

        sorting = { field, direction };
    }

    function sortUsers(users, sorting, hideUsersWithNoJobs, usernameFilter) {
        let cmp = sorting.field == 'id'
            ? (sorting.direction == 'up'
                ? (a, b) => a.id < b.id
                : (a, b) => a.id > b.id)
            : (sorting.direction == 'up'
                ? (a, b) => a[sorting.field] - b[sorting.field]
                : (a, b) => b[sorting.field] - a[sorting.field]);

        if (hideUsersWithNoJobs)
            users = users.filter(u => u.totalJobs > 0);

        if (usernameFilter)
            users = users.filter(u => u.id.includes(usernameFilter));

        return users.sort(cmp);
    }

    const usersQuery = operationStore(`
    query($filter: [JobFilter!]!) {
        jobsStatistics(filter: $filter, groupBy: USER) {
            id
            totalJobs
            totalWalltime
            totalCoreHours
        }
    }
    `, { filter: [] });

    let clusters = [];
    let errorMessage = null;
    getClient()
        .query(`query {
            clusters { clusterID }
        }`)
        .toPromise()
        .then((res) => {
            if (res.error) {
                errorMessage = res.error.message;
                console.error(res.error);
                return;
            }

            clusters = res.data.clusters.map(c => c.clusterID);
        });

    function updateFilters() {
        let filters = [];
        if (clusterId != null)
            filters.push({ clusterId: { eq: clusterId } });
        if (startTime != null)
            filters.push({ startTime: { from: startTime.toISOString(), to: null } });
        if (stopTime != null)
            filters.push({ startTime: { from: null, to: stopTime.toISOString() } });

        $usersQuery.variables = { filter: filters };
    }

    function dateSelected() {
        startTime = new Date(rawStartTime || 0);
        stopTime = new Date(rawStopTime || Date.now());
        updateFilters();    
    }

    $: dateSelected(rawStartTime, rawStopTime);

    const getUserUrl = typeof USERVIEW_URL !== 'undefined'
        ? USERVIEW_URL
        : userId => `/monitoring/user/${userId}`;

    query(usersQuery);
</script>

<style>
    th[scope="col"] > :global(button) {
        float: right;
    }
</style>

<Row>
    {#if errorMessage != null}
        <Col xs="auto">
            <Alert color="danger">{errorMessage}</Alert>
        </Col>
    {/if}
    <Col xs="auto">
        <InputGroup>
            <InputGroupText><Icon name="person-circle" /></InputGroupText>
            <input class="form-control" type="text"
                bind:value={usernameFilter} placeholder="Filter users" />
            <InputGroupText>
                Hide 0 job users:
            </InputGroupText>
            <InputGroupText>
                <input bind:checked={hideUsersWithNoJobs}
                    style="margin-bottom: 0px;" type="checkbox" />
            </InputGroupText>
        </InputGroup>
    </Col>
    <Col xs="auto">
        <InputGroup>
            <InputGroupText><Icon name="cpu"/></InputGroupText>
            <InputGroupText>
                Cluster
            </InputGroupText>
            <select class="form-select" bind:value={clusterId} on:change={updateFilters}>
                <option value={null}>Any</option>
                {#each clusters as cluster}
                    <option value={cluster}>{cluster}</option>
                {/each}
            </select>
        </InputGroup>
    </Col>
    <Col xs="auto">
        <InputGroup>
            <InputGroupText><Icon name="calendar-range" /></InputGroupText>
            <InputGroupText>
                Start Time between
            </InputGroupText>
            <input class="form-control" type="date" bind:value={rawStartTime} />
            <InputGroupText>and</InputGroupText>
            <input class="form-control" type="date" bind:value={rawStopTime} />
        </InputGroup>
    </Col>
</Row>
<Table>
    <thead>
        <tr>
            <th scope="col">
                Username
                <Button color="{sorting.field == 'id' ? 'primary' : 'light'}"
                    size="sm" on:click={e => changeSorting(e, 'id')}>
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
            {#each sortUsers($usersQuery.data.jobsStatistics, sorting, hideUsersWithNoJobs, usernameFilter) as user (user.id)}
                <tr>
                    <td>
                        <a href="{getUserUrl(user.id)}" target="_blank">
                            {user.id}
                        </a>
                    </td>
                    <td>{user.totalJobs}</td>
                    <td>{user.totalWalltime.toFixed(2)}</td>
                    <td>{user.totalCoreHours.toFixed(2)}</td>
                </tr>
            {:else}
                <tr>
                    <td colspan="4">
                        <i>No Users</i>
                    </td>
                </tr>
            {/each}
        {/if}
    </tbody>
</Table>
