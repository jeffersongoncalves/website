@blaze(fold: true)
@props(['variant' => null, 'title' => null])
<span @class(['badge', 'badge-'.$variant => $variant])@if($title !== null) title="{{ $title }}"@endif>{{ $slot }}</span>
