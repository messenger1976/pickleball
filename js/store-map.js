(function () {
    var config = window.fhStoreMap;
    if (!config) {
        return;
    }

    var inlineFrame = document.getElementById('storeMapFrame');
    var fullscreenFrame = document.getElementById('storeMapFullscreenFrame');
    var modal = document.getElementById('storeMapModal');
    var openBtn = document.getElementById('storeMapFullscreenBtn');
    if (!inlineFrame || !fullscreenFrame || !modal || !openBtn) {
        return;
    }

    var lat = Number(config.lat) || 9.6729;
    var lng = Number(config.lng) || 123.8733;

    inlineFrame.src = buildEmbedUrl(lat, lng, 0.008);
    geocodeAndUpdate();

    openBtn.addEventListener('click', openFullscreen);
    modal.querySelectorAll('[data-store-map-close]').forEach(function (el) {
        el.addEventListener('click', closeFullscreen);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
            closeFullscreen();
        }
    });

    function buildEmbedUrl(mapLat, mapLng, pad) {
        var minLng = mapLng - pad;
        var minLat = mapLat - pad;
        var maxLng = mapLng + pad;
        var maxLat = mapLat + pad;
        return 'https://www.openstreetmap.org/export/embed.html?bbox=' +
            encodeURIComponent(minLng + ',' + minLat + ',' + maxLng + ',' + maxLat) +
            '&layer=mapnik&marker=' + encodeURIComponent(mapLat + ',' + mapLng);
    }

    function setLocation(newLat, newLng) {
        lat = newLat;
        lng = newLng;
        inlineFrame.src = buildEmbedUrl(lat, lng, 0.008);
        if (!modal.hasAttribute('hidden')) {
            fullscreenFrame.src = buildEmbedUrl(lat, lng, 0.004);
        }
    }

    function geocodeAndUpdate() {
        var address = (config.geocodeQuery || config.address || '').trim();
        if (!address || !window.fetch) {
            return;
        }

        var url = 'https://nominatim.openstreetmap.org/search?' + new URLSearchParams({
            format: 'json',
            q: address,
            countrycodes: 'ph',
            limit: '1'
        }).toString();

        fetch(url, {
            headers: { Accept: 'application/json' }
        }).then(function (res) {
            return res.ok ? res.json() : null;
        }).then(function (data) {
            if (!data || !data[0] || !data[0].lat || !data[0].lon) {
                return;
            }
            setLocation(parseFloat(data[0].lat), parseFloat(data[0].lon));
        }).catch(function () {
            // Keep fallback center
        });
    }

    function openFullscreen() {
        fullscreenFrame.src = buildEmbedUrl(lat, lng, 0.004);
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('store-map-modal-open');
    }

    function closeFullscreen() {
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('store-map-modal-open');
        fullscreenFrame.removeAttribute('src');
    }
})();
