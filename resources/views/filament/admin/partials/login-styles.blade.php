{{-- Load site styles only on the admin login so the inlined home preview
     renders with its real CSS. Tailwind v4 + custom site.css. --}}
@vite(['resources/css/site.css', 'resources/js/app.js'])
