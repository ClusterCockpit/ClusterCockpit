<script>
    import { mutation } from '@urql/svelte';
    import { Icon, Button, ListGroupItem, Spinner, Modal,
             ModalBody, ModalHeader, ModalFooter, Alert } from 'sveltestrap';
    import { fuzzySearchTags } from '../Common/utils.js';
    import Tag from '../Common/Tag.svelte';

    export let job;
    export let allTags;

    let newTagType = '';
    let newTagName = '';
    let allTagsFiltered;
    let filterTerm = '';
    let pendingChange = false;
    let isOpen = false;

    const createTagMutation = mutation({
        query: `mutation($type: String!, $name: String!) {
            createTag(type: $type, name: $name) { id, tagType, tagName }
        }`
    });

    const addTagsToJobMutation = mutation({
        query: `mutation($job: ID!, $tagIds: [ID!]!) {
            addTagsToJob(job: $job, tagIds: $tagIds) { id, tagType, tagName }
        }`
    });

    const removeTagsFromJobMutation = mutation({
        query: `mutation($job: ID!, $tagIds: [ID!]!) {
            removeTagsFromJob(job: $job, tagIds: $tagIds) { id, tagType, tagName }
        }`
    });

    $: allTagsFiltered = fuzzySearchTags(filterTerm, allTags);

    $: {
        newTagType = '';
        newTagName = '';
        let parts = filterTerm.split(':').map(s => s.trim());
        if (parts.length == 2 && parts.every(s => s.length > 0)) {
            newTagType = parts[0];
            newTagName = parts[1];
        }
    }

    function isNewTag(tagType, tagName) {
        for (let tag of allTagsFiltered)
            if (tag.tagType == tagType && tag.tagName == tagName)
                return false;
        return true;
    }

    function createTag(tagType, tagName) {
        pendingChange = true;
        return createTagMutation({ type: tagType, name: tagName })
            .then(res => {
                if (res.error)
                    throw res.error;

                pendingChange = false;
                allTags = [...allTags, res.data.createTag];
                newTagType = '';
                newTagName = '';
                return res.data.createTag;
            }, err => console.error(err));
    }

    function addTagToJob(tag) {
        pendingChange = tag.id;
        addTagsToJobMutation({ job: job.id, tagIds: [tag.id] })
            .then(res => {
                if (res.error)
                    throw res.error;

                job.tags = res.data.addTagsToJob;
                pendingChange = false;
            })
            .catch(err => console.error(err));
    }

    function removeTagFromJob(tag) {
        pendingChange = tag.id;
        removeTagsFromJobMutation({ job: job.id, tagIds: [tag.id] })
            .then(res => {
                if (res.error)
                    throw res.error;

                job.tags = res.data.removeTagsFromJob;
                pendingChange = false;
            })
            .catch(err => console.error(err));
    }
</script>

<style>
    ul.list-group {
        max-height: 450px;
        margin-bottom: 10px;
        overflow: scroll;
    }
</style>

<Modal {isOpen} toggle={() => (isOpen = !isOpen)}>
    <ModalHeader>
        Manage Tags
        {#if pendingChange !== false}
            <Spinner size="sm" secondary />
        {:else}
            <Icon name="tags" />
        {/if}
    </ModalHeader>
    <ModalBody>
        <input style="width: 100%;"
            type="text" placeholder="Search Tags"
            bind:value={filterTerm} />

        <Alert color="info">
            Search using "<code>type: name</code>". If no tag matches your search,
            a button for creating a new one will appear.
        </Alert>

        <ul class="list-group">
            {#each allTagsFiltered as tag}
                <ListGroupItem>
                    <Tag tag={tag}/>

                    <span style="float: right;">
                        {#if pendingChange === tag.id}
                            <Spinner size="sm" secondary />
                        {:else if job.tags.find(t => t.id == tag.id)}
                            <Button size="sm" outline color="danger"
                                on:click={() => removeTagFromJob(tag)}>
                                <Icon name="x" />
                            </Button>
                        {:else}
                            <Button size="sm" outline color="success"
                                on:click={() => addTagToJob(tag)}>
                                <Icon name="plus" />
                            </Button>
                        {/if}
                    </span>
                </ListGroupItem>
            {:else}
                <ListGroupItem disabled>
                    <i>No tags matching</i>
                </ListGroupItem>
            {/each}
        </ul>
        <br/>
        {#if newTagType && newTagName && isNewTag(newTagType, newTagName)}
            <Button outline color="success"
                on:click={e => (e.preventDefault(), createTag(newTagType, newTagName))
                    .then(tag => addTagToJob(tag))}>
                Create & Add Tag:
                <Tag tag={({ tagType: newTagType, tagName: newTagName })}></Tag>
            </Button>
        {:else if allTagsFiltered.length == 0}
            <Alert>Search Term is not a valid Tag (<code>type: name</code>)</Alert>
        {/if}
    </ModalBody>
    <ModalFooter>
        <Button color="primary" on:click={() => (isOpen = false)}>Close</Button>
    </ModalFooter>
</Modal>

<Button outline on:click={() => (isOpen = true)}>
    Manage Tags <Icon name="tags" />
</Button>
