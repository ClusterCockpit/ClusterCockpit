import { initClient } from '@urql/svelte';
import { filter, map, merge, pipe, share, tap } from 'wonka';
import { dedupExchange, fetchExchange } from '@urql/core';

let clientInitialized = null;
export const waitForClientInit = new Promise((resolve) => {
    clientInitialized = resolve;
});

export function initGraphQL(ccconfig) {
    const jwt = ccconfig['jwt'];
    let client = initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`,
        fetchOptions: () => ({
            headers: {
                'Authorization': `Bearer ${jwt}`
            }
        }),
        exchanges: [
            dedupExchange,
            expiringCacheExchange({
                ttl: 5 * 60 * 1000,
                maxSize: 150,
            }),
            fetchExchange
        ]
    });
    clientInitialized(client);
}

/*
 * Alternative to the default cacheExchange from urql (A GraphQL client).
 * Mutations do not invalidate cached results, so in that regard, this
 * implementation is inferior to the default one. Most people should probably
 * use the standard cacheExchange and @urql/exchange-request-policy. This cache
 * also ignores the 'network-and-cache' request policy.
 *
 * Options:
 *    ttl: How long queries are allowed to be cached (in milliseconds)
 *    maxSize: Max number of results cached. The oldest queries are removed first.
 */
const expiringCacheExchange = ({ ttl, maxSize }) => ({ forward }) => {
    const cache = new Map();
    const isCached = (operation) => {
        if (operation.kind !== 'query' || operation.context.requestPolicy === 'network-only')
            return false;

        if (!cache.has(operation.key))
            return false;

        let cacheEntry = cache.get(operation.key);
        return Date.now() < cacheEntry.expiresAt;
    };

    return operations => {
        let shared = share(operations);
        return merge([
            pipe(
                shared,
                filter(operation => isCached(operation)),
                map(operation => cache.get(operation.key).response)
            ),
            pipe(
                shared,
                filter(operation => !isCached(operation)),
                forward,
                tap(response => {
                    if (!response.operation || response.operation.kind !== 'query')
                        return;

                    if (!response.data)
                        return;

                    let now = Date.now();
                    for (let cacheEntry of cache.values()) {
                        if (cacheEntry.expiresAt < now) {
                            cache.delete(cacheEntry.response.operation.key);
                        }
                    }

                    if (cache.size > maxSize) {
                        let n = cache.size - maxSize + 1;
                        for (let key of cache.keys()) {
                            if (n-- == 0)
                                break;

                            cache.delete(key);
                        }
                    }

                    cache.set(response.operation.key, {
                        expiresAt: now + ttl,
                        response: response
                    });
                })
            )
        ]);
    };
};
