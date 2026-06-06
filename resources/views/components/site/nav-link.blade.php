@blaze
@props(['href', 'active' => false, 'mobile' => false])
<a href="{{ $href }}" @class([$mobile ? 'mobile-nav-link' : 'nav-link', 'is-active' => $active])@if($active) aria-current="page"@endif>{{ $slot }}</a>
