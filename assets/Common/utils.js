import { waitForClientInit } from './gqlclient.js';
import { getClient } from '@urql/svelte';
import { readable } from 'svelte/store';

function fuzzyMatch(term, string) {
    return string.toLowerCase().includes(term);
}

export function fuzzySearchTags(term, tags) {
    if (!tags)
        return [];

    let results = [];
    let termparts = term.split(':').map(s => s.trim()).filter(s => s.length > 0);

    if (termparts.length == 0) {
        results = tags.slice();
    } else if (termparts.length == 1) {
        for (let tag of tags)
            if (fuzzyMatch(termparts[0], tag.tagType)
                || fuzzyMatch(termparts[0], tag.tagName))
                results.push(tag);
    } else if (termparts.length == 2) {
        for (let tag of tags)
            if (fuzzyMatch(termparts[0], tag.tagType)
                && fuzzyMatch(termparts[1], tag.tagName))
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
        tags { id, tagName, tagType }
    }`;

    waitForClientInit.then(client => client.query(query).toPromise()).then(({ error, data }) => {
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

export function arraysEqual(a, b) {
    if (a === b)
        return true;

    if (a == null || b == null || a.length !== b.length)
        return false;

    for (let i = 0; i < a.length; i++)
        if (a[i] !== b[i])
            return false;

    return true;
}

export function formatNumber(x) {
    let suffix = '';
    if (x >= 1000000000) {
        x /= 1000000;
        suffix = 'G';
    } else if (x >= 1000000) {
        x /= 1000000;
        suffix = 'M';
    } else if (x >= 1000) {
        x /= 1000;
        suffix = 'k';
    }

    return `${(Math.round(x * 100) / 100)}${suffix}`;
}
