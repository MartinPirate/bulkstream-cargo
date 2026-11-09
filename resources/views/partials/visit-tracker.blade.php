{{-- Reports one pageview per browser session to the marketing engine. Rendered only when ME_TRACKING_ID is set. --}}
<script>
(function () {
    var tid = {{ \Illuminate\Support\Js::from($trackingId) }};
    var url = {{ \Illuminate\Support\Js::from($trackUrl) }};
    var sid;
    try {
        sid = sessionStorage.getItem('bs_sid');
        if (!sid) {
            sid = Math.random().toString(36).slice(2, 11) + Date.now().toString(36);
            sessionStorage.setItem('bs_sid', sid);
        }
    } catch (e) {
        sid = Math.random().toString(36).slice(2, 11) + Date.now().toString(36);
    }
    var params = new URLSearchParams(window.location.search);
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        keepalive: true,
        body: JSON.stringify({
            trackingId: tid,
            sessionId: sid,
            referrer: document.referrer,
            landingPage: window.location.pathname,
            utmSource: params.get('utm_source'),
            utmMedium: params.get('utm_medium'),
            utmCampaign: params.get('utm_campaign'),
        }),
    }).catch(function (e) { console.warn('visit tracker:', e); });
})();
</script>
