<script>
    import { mutation } from '@urql/svelte';
    import { Icon, Button, ListGroup, ListGroupItem, Spinner,
             Modal, ModalBody, ModalHeader, ModalFooter, Alert,
             Input, InputGroup, InputGroupText, } from 'sveltestrap';
    import { getColorForTag, fuzzySearchTags } from './utils.js';

    export let job;
    export let allTags;

    let newTagType = '';
    let newTagName = '';
    let createDeleteTagsOpen = false;
    let addRemoveTagsOpen = false;
    let allTagsFiltered = [...allTags];
    let allTagsFilterTerm = '';
    let pendingChange = false;

    const createTagMutation = mutation({
        query: `mutation($type: String!, $name: String!) {
            createTag(type: $type, name: $name) { id, tagType, tagName }
        }`
    });

    const deleteTagMutation = mutation({
        query: `mutation($id: ID!) {
            deleteTag(id: $id)
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

    $: allTagsFiltered = fuzzySearchTags(allTagsFilterTerm, allTags);

    function createTag(tagType, tagName) {
        pendingChange = true;
        createTagMutation({ type: tagType, name: tagName })
            .then(res => {
                if (res.error)
                    throw res.error;

                pendingChange = false;
                allTags.push(res.data.createTag);
                allTags = allTags; // Let Svelte do its magic...
            }, err => console.error(err));
    }

    function deleteTag(tag) {
        pendingChange = true;
        deleteTagMutation({ id: tag.id })
            .then(res => {
                if (res.error)
                    throw res.error;

                pendingChange = false;
                allTags = allTags.filter(({ id }) => id != tag.id);
                job.tags = job.tags.filter(({ id }) => id != tag.id);
                job = job; // Let Svelte do its magic...
            })
            .catch(err => console.error(err));
    }

    function addTagToJob(tag) {
        pendingChange = tag.id;
        addTagsToJobMutation({ job: job.id, tagIds: [tag.id] })
            .then(res => {
                if (res.error)
                    throw res.error;

                job.tags = res.data.addTagsToJob;
                job = job; // Let Svelte do its magic...
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
                job = job; // Let Svelte do its magic...
                pendingChange = false;
            })
            .catch(err => console.error(err));
    }
</script>

<Modal isOpen={createDeleteTagsOpen} toggle={() => (createDeleteTagsOpen = !createDeleteTagsOpen)}>
    <ModalHeader>
        Create/Delete Tags
        {#if pendingChange === true}
            <Spinner secondary />
        {/if}
    </ModalHeader>
    <ModalBody>
        <input style="width: 100%;"
            type="text" placeholder="Fuzzy Search Tags"
            bind:value={allTagsFilterTerm} />

        <Alert color="warning">
            Warning: Deleting a tag here will also remove
            the tag from <b>all</b> jobs!
        </Alert>

        <ListGroup>
            {#each allTagsFiltered as tag}
                <ListGroupItem>
                    <span class="badge rounded-pill {getColorForTag(tag)}">
                        {tag.tagType}: {tag.tagName}
                    </span>

                    <span style="float: right;">
                        <Button outline color="danger"
                            on:click={() => deleteTag(tag)}>
                            <Icon name="x-circle" />
                        </Button>
                    </span>
                </ListGroupItem>
            {:else}
                <ListGroupItem disabled>
                    <i>No Tags</i>
                </ListGroupItem>
            {/each}
        </ListGroup>
        <br/>
        <InputGroup>
            <Input type="text" bind:value={newTagType} placeholder="Tag Type" />
            <Input type="text" bind:value={newTagName} placeholder="Tag Name" />
            <Button
                disabled={!newTagType || !newTagName || pendingChange !== false ? 'disabled' : undefined}
                outline on:click={() => createTag(newTagType, newTagName)}>
                Create Tag
            </Button>
        </InputGroup>
    </ModalBody>
    <ModalFooter>
        <Button color="primary" on:click={() => (createDeleteTagsOpen = false)}>Close</Button>
    </ModalFooter>
</Modal>

<Modal isOpen={addRemoveTagsOpen} toggle={() => (addRemoveTagsOpen = !addRemoveTagsOpen)}>
    <ModalHeader>
        Add/Remove Tags
    </ModalHeader>
    <ModalBody>
        <input style="width: 100%;"
            type="text" placeholder="Fuzzy Search Tags"
            bind:value={allTagsFilterTerm} />

        <ListGroup>
            {#each allTagsFiltered as tag}
                <ListGroupItem>
                    <span class="badge rounded-pill {getColorForTag(tag)}">
                        {tag.tagType}: {tag.tagName}
                    </span>

                    <span style="float: right;">
                        {#if pendingChange === tag.id}
                            <Spinner secondary />
                        {:else if job.tags.find(t => t.id == tag.id)}
                            <Button outline color="danger"
                                on:click={() => removeTagFromJob(tag)}>
                                <Icon name="x" />
                            </Button>
                        {:else}
                            <Button outline color="success"
                                on:click={() => addTagToJob(tag)}>
                                <Icon name="plus" />
                            </Button>
                        {/if}
                    </span>
                </ListGroupItem>
            {:else}
                <ListGroupItem disabled>
                    <i>No Tags</i>
                </ListGroupItem>
            {/each}
        </ListGroup>
    </ModalBody>
    <ModalFooter>
        <Button color="primary" on:click={() => (addRemoveTagsOpen = false)}>Close</Button>
    </ModalFooter>
</Modal>

<Button outline on:click={() => (addRemoveTagsOpen = true)}>
    Add/Remove Tag to this Job
    <Icon name="tag" />
</Button>

<Button outline on:click={() => (createDeleteTagsOpen = true)}>
    Create/Delete Tags
    <Icon name="tags" />
</Button>
