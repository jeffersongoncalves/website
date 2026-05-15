@props(['number' => null])
<div class="editorial-eyebrow">@if($number){{ $number }} · @endif{{ $slot }}</div>
