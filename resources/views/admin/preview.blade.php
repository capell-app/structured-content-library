<article class="fi-prose">
    <h2>{{ $item->title }}</h2>
    @if ($item->summary)
        <p>{{ strip_tags($item->summary) }}</p>
    @endif
    @if ($item->content)
        <p>{{ strip_tags($item->content) }}</p>
    @endif
    @foreach ($item->payload as $value)
        <p>{{ $value }}</p>
    @endforeach
</article>
