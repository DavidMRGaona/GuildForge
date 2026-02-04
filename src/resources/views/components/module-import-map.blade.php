@if(count($imports) > 0)
<script type="importmap">
{
    "imports": @json($imports, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
}
</script>
@endif
