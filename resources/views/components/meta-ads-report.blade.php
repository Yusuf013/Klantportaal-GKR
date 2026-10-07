{{--
    Meta-versie van het advertentieblok.
    De echte opbouw staat nu in components/ads-report.blade.php, zodat Meta en
    Google Ads hetzelfde blok gebruiken. Dit bestand blijft bestaan, zodat de
    Meta-pagina's niet aangepast hoeven te worden.
--}}
@props(['report' => null, 'error' => null, 'days' => 28, 'explain' => true])

<x-ads-report platform="meta" :report="$report" :error="$error" :days="$days" :explain="$explain" />