<!-- 
    A wrapper component for Plots that should be resized
    automatically. If you want to use this to resize plots
    inside of Bootstrap `<Col></Col>` Tags, you MUST specify
    `xs`.
-->
<script>
    let width = 0;
</script>

<div bind:clientWidth={width}>
    <slot width={width} />
</div>
