@blaze(fold: true)
@props(['num' => null, 'label' => ''])

<div class="sec-num">
    @if($num)§{{ $num }} · @endif{{ $label }}
</div>
