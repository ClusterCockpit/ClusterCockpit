import { initClient, getClient } from '@urql/svelte';
import { readable } from 'svelte/store';

function fuzzyMatch(term, string) {
    return string.toLowerCase().includes(term);
}

export function fuzzySearchTags(term, tags) {
    if (!tags)
        return [];

    let results = [];
    for (let tag of tags) {
        if (fuzzyMatch(term, `${tag.tagType}: ${tag.tagName}`))
            results.push(tag);
    }

    return results.sort((a, b) => {
        if (a.tagType < b.tagType) return -1;
        if (a.tagType > b.tagType) return 1;
        if (a.tagName < b.tagName) return -1;
        if (a.tagName > b.tagName) return 1;
        return 0;
    });
}

export const clustersQuery = readable({ fetching: true }, (set) => {
    initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
        ? GRAPHQL_BACKEND
        : `${window.location.origin}/query`
    });

    const query = `
    query {
        clusters {
            clusterID,
            flopRateScalar,
            flopRateSimd,
            memoryBandwidth,
           metricConfig {
                name, unit, peak,
                normal, caution, alert
            }
            filterRanges {
                duration { from, to }
                numNodes { from, to }
                startTime { from, to }
            }
        }
        filterRanges {
            duration { from, to }
            numNodes { from, to }
            startTime { from, to }
        }
        tags { id, tagName, tagType }
    }`;

    getClient().query(query).toPromise().then(({ error, data }) => {
        if (error) {
            console.error(error);
            return set({ error });
        }

        const metricConfig = {};
        const metricUnits = {};

        for (let cluster of data.clusters) {
            metricConfig[cluster.clusterID] = {};
            for (let config of cluster.metricConfig) {
                metricConfig[cluster.clusterID][config.name] = config;

                if (metricUnits[config.name] == null) {
                    metricUnits[config.name] = config.unit;
                    continue;
                }

                if (metricUnits[config.name] == config.unit)
                    continue;

                metricUnits[config.name] += ', ' + config.unit;
                console.warn(`unit for metric '${config.name}' differs: ${metricUnits[config.name]}`);
            }
        }

        set({
            tags: data.tags,
            clusters: data.clusters,
            filterRanges: data.filterRanges,
            metricConfig,
            metricUnits
        });
    })

    return () => {};
});

export function tilePlots(plotsPerRow, arr) {
    let rows = [], i = 0;
    for (let n = 0; n < arr.length; n += plotsPerRow) {
        let row = [];
        for (let m = 0; m < plotsPerRow; m++, i++) {
            if (i < arr.length)
                row.push(arr[i]);
            else
                row.push(null);
        }
        rows.push(row);
    }
    return rows;
}
