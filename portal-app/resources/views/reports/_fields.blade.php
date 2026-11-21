<div class="grid g2">
  <div class="field"><label>Title</label><input type="text" name="title" value="{{ old('title', $d->title) }}" required placeholder="e.g. Monthly performance"></div>
  <div class="field"><label>Order</label><input type="number" name="sort" value="{{ old('sort', $d->sort ?? 0) }}" min="0"></div>
</div>
<div class="field"><label>Power BI link</label><input type="text" name="embed_url" value="{{ old('embed_url', $d->embed_url) }}" required placeholder="https://app.powerbi.com/reportEmbed?reportId=…">@error('embed_url')<span class="errors">{{ $message }}</span>@enderror</div>
<div class="field"><label>Description</label><input type="text" name="description" value="{{ old('description', $d->description) }}"></div>
<fieldset class="row small" style="border:0;padding:0;margin:0"><legend class="small muted" style="margin-bottom:4px">Who sees it</legend>
  @foreach($roles as $k => $l)<label class="row"><input type="checkbox" name="roles[]" value="{{ $k }}" @checked(in_array($k, old('roles', $d->roles ?? []), true))> {{ $l }}</label>@endforeach</fieldset>
