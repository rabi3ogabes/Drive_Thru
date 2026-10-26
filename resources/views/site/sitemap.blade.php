{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($paths as $path)
@foreach ($locales as $locale)
    <url>
        <loc>{{ url($path).'?lang='.$locale }}</loc>
@foreach ($locales as $alternate)
        <xhtml:link rel="alternate" hreflang="{{ $alternate }}" href="{{ url($path).'?lang='.$alternate }}"/>
@endforeach
        <changefreq>weekly</changefreq>
        <priority>{{ $path === '/' ? '1.0' : '0.8' }}</priority>
    </url>
@endforeach
@endforeach
</urlset>
