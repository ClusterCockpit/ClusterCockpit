export function getColorForTag(tag) {
    /* TODO: Make this configurable? */
    if (tag.tagType == 'pathological' || tag.tagName == 'pathological')
        return 'bg-danger';

    if (tag.tagType == 'bottleneck' || tag.tagName == 'bottleneck')
        return 'bg-warning';

    return 'bg-info';
}
