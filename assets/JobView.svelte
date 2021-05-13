<script>
    import { Col, Row } from 'sveltestrap';

    export let jobId;
    export let clusterId;
</script>

<style>

</style>

<Row>
    <Col>
        JobId: {jobId}, clusterId: {clusterId}
    </Col>
</Row>
