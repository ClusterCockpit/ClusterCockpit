<script>
    import { onMount, onDestroy } from "svelte";
    import { tilePlots } from "../Common/utils.js";

    export let itemsPerRow;
    export let items;
    $: rows = tilePlots(itemsPerRow, items);

    let observer = null;
    let trs = {};
    let tableWidth;

    // const onIntersection = (entry) => {
    // };

    // onMount(() => {
    //     observer = new IntersectionObserver((entries) => {
    //         for (let entry of entries)
    //             if (entry.intersectionRatio > 0)
    //                 onIntersection(entry)
    //     })
    // });

    // onDestroy(() => {
    //     if (observer != null)
    //         observer.disconnect();
    // });
</script>

<table bind:clientWidth={tableWidth} style="width: 100%; table-layout: fixed;">
    {#each rows as row, i}
        <tr bind:this={trs[i]}>
            {#each row as col}
                <td>
                    <slot item={col} width={(tableWidth / itemsPerRow) - (10 * itemsPerRow)}></slot>
                </td>
            {/each}
        </tr>
    {/each}
</table>
