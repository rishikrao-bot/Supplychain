@if($paginator->hasPages())
<nav class="row" aria-label="Pages">
  @if($paginator->onFirstPage())<span class="btn sm" aria-disabled="true">← Previous</span>@else<a class="btn sm" href="{{ $paginator->previousPageUrl() }}">← Previous</a>@endif
  <span class="small muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
  @if($paginator->hasMorePages())<a class="btn sm" href="{{ $paginator->nextPageUrl() }}">Next →</a>@else<span class="btn sm" aria-disabled="true">Next →</span>@endif
</nav>
@endif
