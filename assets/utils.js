import { getClient } from '@urql/svelte';

export function getColorForTag(tag) {
    /* TODO: Make this configurable? */
    if (tag.tagType == 'pathological' || tag.tagName == 'pathological')
        return 'bg-danger';

    if (tag.tagType == 'bottleneck' || tag.tagName == 'bottleneck')
        return 'bg-warning';

    return 'bg-info';
}

function fuzzyMatch(term, string) {
    return string.toLowerCase().includes(term);
}

export function fuzzySearchTags(term, tags) {
    if (!tags)
        return [];

    let results = [];
    for (let tag of tags) {
        if (fuzzyMatch(term, tag.tagType) ||
            fuzzyMatch(term, tag.tagName))
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

/*
 * Fetch a list of all clusters and build:
 *
 * metricConfig[<clusterId>][<metricName>] = { name, unit, preak, ... };
 * metricUnits[<metricName>] = unit
 */
export async function fetchClusters(metricConfig = {}, metricUnits = {}) {
    const query = getClient().query(`query {
            clusters {
                clusterID,
                metricConfig {
                    name
                    unit
                    peak
                    normal
                    caution
                    alert
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
        }`);

    const res = await query.toPromise();
    if (res.error)
        throw res.error;

    for (let cluster of res.data.clusters) {
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

    return {
        clusters: res.data.clusters,
        filterRanges: res.data.filterRanges,
        metricConfig,
        metricUnits
    };
}
