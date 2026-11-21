{{-- Move the window of weeks. Needs $weeks; keeps other query parameters. --}}
@php($n = count($weeks))
<div class="row">
  <a class="btn sm" href="{{ request()->fullUrlWithQuery(['from' => $weeks[0]->copy()->subWeeks(4)->toDateString()]) }}">← 4 weeks</a>
  <a class="btn sm" href="{{ request()->fullUrlWithQuery(['from' => null]) }}">This week</a>
  <a class="btn sm" href="{{ request()->fullUrlWithQuery(['from' => $weeks[0]->copy()->addWeeks(4)->toDateString()]) }}">4 weeks →</a>
  <span class="small muted">w/e {{ $weeks[0]->format('j M') }} – {{ end($weeks)->format('j M Y') }}</span>
  <span class="small muted">Show</span>@foreach([8, 12, 26] as $w)<a class="btn sm {{ $n === $w ? 'primary' : '' }}" href="{{ request()->fullUrlWithQuery(['weeks' => $w]) }}">{{ $w }} weeks</a>@endforeach
</div>
