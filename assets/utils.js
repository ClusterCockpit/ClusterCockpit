import { getClient } from '@urql/svelte';

export function getColorForTag(tag) {
    /* TODO: Make this configurable? */
    if (tag.tagType == 'pathological' || tag.tagName == 'pathological')
        return 'bg-danger';

    if (tag.tagType == 'bottleneck' || tag.tagName == 'bottleneck')
        return 'bg-warning';

    return 'bg-info';
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
