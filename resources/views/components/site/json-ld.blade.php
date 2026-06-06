@blaze
@props(['data'])
{{-- JSON_HEX_TAG escapes < and > so embedded content can't break out of the
     <script> with a literal </script>. --}}
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
