<script>
    export let tag; /* { id: String, tagType: String, tagName: String } */

    const getTagUrl = typeof TAG_URL !== 'undefined'
        ? TAG_URL
        : tag => `/monitoring/tag/${tag.id}`;
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

<a target="_blank" href="{getTagUrl(tag)}">
    <span class="badge bg-warning text-dark">{tag.tagType}: {tag.tagName}</span>
</a>

