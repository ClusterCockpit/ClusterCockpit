<script>
    export let tag; /* { id: String, tagType: String, tagName: String } */
</script>

<style>
    a {
        margin-left: 0.5rem;
        line-height: 2;
    }
    span {
        font-size: 0.9rem;
    }
</style>

<a target="_blank" href="{tag.id != null ? "/monitoring/tag/" + tag.id : "#"}">
    <span class="badge bg-warning text-dark">{tag.tagType}: {tag.tagName}</span>
</a>
