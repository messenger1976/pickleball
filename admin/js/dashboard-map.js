(function() {
    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initDashboardMap() {
        var config = window.fhDashboardMap;
        if (!config || !window.L) {
            return;
        }

        var mapEl = document.getElementById('customerMap');
        var statusEl = document.getElementById('mapGeocodeStatus');
        if (!mapEl) {
            return;
        }

        var center = config.defaultCenter || { lat: 9.6729, lng: 123.8733 };
        var map = L.map(mapEl, {
            scrollWheelZoom: true
        }).setView([center.lat, center.lng], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        var markers = [];
        var bounds = L.latLngBounds([]);
        var geocodeQueue = [];
        var failedCount = 0;
        var totalToGeocode = 0;

        function setStatus(message) {
            if (statusEl) {
                statusEl.textContent = message;
            }
        }

        function locationTitle(location) {
            if (location.type === 'business' || location.type === 'venue') {
                return 'Venue';
            }
            return 'Customer';
        }

        function buildPopupContent(location) {
            var title = locationTitle(location);
            var html = '<div style="max-width:240px;font-size:13px;line-height:1.45;">';
            html += '<strong>' + escapeHtml(location.name) + '</strong><br>';
            html += '<span style="color:#64748b;">' + title + '</span><br>';
            if (location.address) {
                html += escapeHtml(location.address);
            }
            if (location.phone) {
                html += '<br>Phone: ' + escapeHtml(location.phone);
            }
            if (location.email) {
                html += '<br>Email: ' + escapeHtml(location.email);
            }
            html += '</div>';
            return html;
        }

        function addMarker(location, lat, lng) {
            var isBusiness = location.type === 'business' || location.type === 'venue';
            var marker = L.circleMarker([lat, lng], {
                radius: isBusiness ? 10 : 7,
                fillColor: isBusiness ? '#2d6a4f' : '#dc2626',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.95
            }).addTo(map);

            marker.bindPopup(buildPopupContent(location));
            marker.bindTooltip(location.name, { direction: 'top', offset: [0, -8] });
            markers.push(marker);
            bounds.extend([lat, lng]);
        }

        function finishMap() {
            if (markers.length === 1) {
                map.setView(markers[0].getLatLng(), 14);
            } else if (markers.length > 1) {
                map.fitBounds(bounds, { padding: [40, 40] });
            }

            var parts = [];
            parts.push(markers.length + ' pin' + (markers.length === 1 ? '' : 's') + ' on map');
            if (failedCount > 0) {
                parts.push(failedCount + ' address' + (failedCount === 1 ? '' : 'es') + ' could not be located');
            }
            setStatus(parts.join(' · '));
            setTimeout(function() {
                map.invalidateSize();
            }, 100);
        }

        function geocodeAddress(address) {
            return $.ajax({
                type: 'POST',
                url: 'process.php',
                data: {
                    function: 'GeocodeMapAddress',
                    address: address
                },
                dataType: 'json'
            });
        }

        function processQueue(index) {
            if (index >= geocodeQueue.length) {
                finishMap();
                return;
            }

            var item = geocodeQueue[index];
            geocodeAddress(item.address).done(function(res) {
                if (res.validation_status === 'T' && res.lat && res.lng) {
                    addMarker(item, res.lat, res.lng);
                } else {
                    failedCount++;
                }
            }).fail(function() {
                failedCount++;
            }).always(function() {
                setStatus('Plotting addresses… ' + (index + 1) + ' / ' + totalToGeocode);
                setTimeout(function() {
                    processQueue(index + 1);
                }, 1100);
            });
        }

        function collectLocations() {
            var list = [];
            if (Array.isArray(config.venues) && config.venues.length) {
                list = config.venues.slice();
            } else {
                if (config.business) {
                    list.push(config.business);
                }
                if (Array.isArray(config.customers)) {
                    config.customers.forEach(function(customer) {
                        if (customer) {
                            list.push(customer);
                        }
                    });
                }
            }
            return list;
        }

        var locations = collectLocations();
        locations.forEach(function(loc) {
            if (!loc) {
                return;
            }
            if (typeof loc.lat === 'number' && typeof loc.lng === 'number') {
                addMarker(loc, loc.lat, loc.lng);
            } else if (loc.address) {
                geocodeQueue.push(loc);
            }
        });

        totalToGeocode = geocodeQueue.length;
        if (markers.length === 0 && totalToGeocode === 0) {
            setStatus('No addresses to display.');
            return;
        }

        if (totalToGeocode === 0) {
            finishMap();
            return;
        }

        setStatus('Loading map and plotting ' + totalToGeocode + ' address' + (totalToGeocode === 1 ? '' : 'es') + '…');
        processQueue(0);
    }

    if (window.fhDashboardMap) {
        initDashboardMap();
    }
})();
