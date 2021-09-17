import { initClient } from '@urql/svelte';

export function initGraphQL(ccconfig) {
    const jwt = ccconfig['jwt'];
    return initClient({
        url: typeof GRAPHQL_BACKEND !== 'undefined'
            ? GRAPHQL_BACKEND
            : `${window.location.origin}/query`,
        fetchOptions: () => ({
            headers: {
                'Authorization': `Bearer ${jwt}`
            }
        })
    });
}

