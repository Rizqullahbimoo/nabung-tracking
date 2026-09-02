{{-- PWA / Add to Home Screen --}}
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#2952E3">

{{-- iOS Safari standalone support --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Nabung">
<link rel="apple-touch-icon" href="/icons/icon-180.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        });
    }
</script>
