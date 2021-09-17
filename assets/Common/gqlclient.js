import { initClient } from '@urql/svelte';

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
        })
    });
    clientInitialized(client);
}
