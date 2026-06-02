{{-- Thin section wrapper. The whole detail page lives in the reusable
     <x-site.project-detail> component so /projects, /articles and /links
     don't duplicate it — they all route through ProjectViewController to
     this single view, and the component handles the per-section back link,
     breadcrumb and canonical URL. --}}
<x-site.project-detail
    :project="$project"
    :readmeHtml="$readmeHtml"
    :versions="$versions"
    :activeVersion="$activeVersion"
    :ref="$ref"
/>
