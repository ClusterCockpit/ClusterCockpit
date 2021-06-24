<script>
    import { getColorForTag, fuzzySearchTags } from './utils.js';
    export let tag; /* { id: String, tagType: String, tagName: String } */
</script>

<a target="_blank" href="/monitoring/jobs/#tag={tag.id}">
    <span class="cc-tag badge rounded-pill {getColorForTag(tag)}">{tag.tagType}: {tag.tagName}</span>
</a>
