/* Real OpenStreetMap-backed delivery map. Coordinates come from saved address pins and driver GPS. */
(function () {
    'use strict';

    // Keep the delivery view focused on Santa Cruz, Laguna and nearby barangays.
    var santaCruzCenter = [14.2814, 121.4161];
    var santaCruzBounds = L.latLngBounds([[14.20, 121.33], [14.36, 121.50]]);
    var map;
    var markers;
    var routeLayer;
    var routeCache = new Map();
    var lastPoints = [];
    var inFlight = false;
    var hasCenteredOnData = false;
    var stationPoint = null;

    function validPoint(latitude, longitude) {
        var lat = Number(latitude);
        var lng = Number(longitude);
        return Number.isFinite(lat) && Number.isFinite(lng) &&
            Math.abs(lat) <= 90 && Math.abs(lng) <= 180 && !(lat === 0 && lng === 0)
            ? [lat, lng] : null;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character];
        });
    }

    function openMapLink(point) {
        var url = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(point[0] + ',' + point[1]);
        return '<a href="' + url + '" target="_blank" rel="noopener">Open in Google Maps</a>';
    }

    function markerIcon(kind, glyph) {
        return L.divIcon({
            className: 'delivery-map-marker delivery-map-marker--' + kind,
            html: '<span><i>' + glyph + '</i></span>',
            iconSize: [34, 40],
            iconAnchor: [17, 37],
            popupAnchor: [0, -34],
        });
    }

    function addMarker(point, kind, glyph, title, detail, updatedAt) {
        if (!point) return null;
        var timestamp = updatedAt ? new Date(updatedAt) : null;
        var updatedText = timestamp && !Number.isNaN(timestamp.getTime())
            ? 'Last GPS update: ' + timestamp.toLocaleString()
            : '';
        var popup = '<div class="delivery-map-popup"><strong>' + escapeHtml(title) + '</strong>' +
            '<span>' + escapeHtml(detail) + '</span>' +
            (updatedText ? '<span>' + escapeHtml(updatedText) + '</span>' : '') +
            openMapLink(point) + '</div>';
        return L.marker(point, { icon: markerIcon(kind, glyph) }).bindPopup(popup).addTo(markers);
    }

    function update(points, preserveView) {
        if (!map || !markers) return;
        lastPoints = Array.isArray(points) ? points : [];
        markers.clearLayers();
        var bounds = [];
        var drivers = new Set();
        var destinations = 0;
        var activeIds = new Set();

        if (stationPoint) {
            addMarker(stationPoint, 'station', 'A', 'Aqua Clayne water refilling station (official route origin)', (window.deliveryConfig && window.deliveryConfig.stationCoordinates && window.deliveryConfig.stationCoordinates.address) || 'Official delivery starting point');
            bounds.push(stationPoint);
        }

        lastPoints.forEach(function (item) {
            activeIds.add(String(item.id));
            var driverPoint = validPoint(item.driver_latitude, item.driver_longitude);
            var destinationPoint = validPoint(item.destination_latitude, item.destination_longitude);
            if (driverPoint && !santaCruzBounds.contains(driverPoint)) driverPoint = null;
            if (destinationPoint && !santaCruzBounds.contains(destinationPoint)) destinationPoint = null;

            if (driverPoint) {
                drivers.add(item.driver_id || item.id);
                addMarker(
                    driverPoint,
                    'driver',
                    '🚚',
                    'Driver · ' + (item.driver_name || 'Assigned driver'),
                    'Order #' + (item.order_number || '—') + ' · ' + (item.status || 'In transit'),
                    item.location_updated_at,
                );
                bounds.push(driverPoint);
            }

            if (destinationPoint) {
                destinations += 1;
                addMarker(
                    destinationPoint,
                    'destination',
                    '📍',
                    'Delivery destination',
                    (item.address || 'Customer address') + ' · Order #' + (item.order_number || '—'),
                );
                bounds.push(destinationPoint);
            }
            if (destinationPoint) updateRoute(String(item.id), destinationPoint);
            else if (routeCache.has(String(item.id))) {
                routeLayer.removeLayer(routeCache.get(String(item.id)).line);
                routeCache.delete(String(item.id));
            }
        });

        routeCache.forEach(function (entry, id) {
            if (!activeIds.has(id)) { routeLayer.removeLayer(entry.line); routeCache.delete(id); }
        });

        if (!preserveView && !hasCenteredOnData) {
            map.setView(stationPoint || santaCruzCenter, stationPoint ? 15 : 14);
            hasCenteredOnData = true;
        }

        var status = document.getElementById('deliveryMapStatus');
        if (status) {
            status.textContent = bounds.length
                ? drivers.size + ' live driver location' + (drivers.size === 1 ? '' : 's') +
                    ' · ' + destinations + ' mapped destination' + (destinations === 1 ? '' : 's') +
                    ' · refreshes automatically'
                : 'Waiting for GPS or pinned address coordinates in Santa Cruz, Laguna. The map updates automatically.';
        }
    }

    function updateRoute(id, destination) {
        var previous = routeCache.get(id);
        var destinationChanged = previous && L.latLng(previous.destination).distanceTo(L.latLng(destination)) > 10;
        if (previous && !destinationChanged) return;
        if (previous) routeLayer.removeLayer(previous.line);
        var line = L.polyline([], { color: '#0878C9', weight: 5, opacity: 0.85 }).addTo(routeLayer);
        var entry = { line: line, destination: destination };
        routeCache.set(id, entry);
        var routeUrl = window.deliveryConfig && window.deliveryConfig.routeUrl;
        if (!routeUrl) return;
        fetch(routeUrl + '/' + encodeURIComponent(id) + '/route', { headers: { 'Accept': 'application/json' }, cache: 'no-store' }).then(function (response) { if (!response.ok) throw new Error('route'); return response.json(); }).then(function (payload) {
            var current = routeCache.get(id);
            var data = payload.data || {};
            var coordinates = data.coordinates;
            var origin = data.origin;
            if (origin && Number.isFinite(Number(origin.latitude)) && Number.isFinite(Number(origin.longitude))) {
                stationPoint = [Number(origin.latitude), Number(origin.longitude)];
            }
            var route = { distance: data.distance, duration: data.duration };
            if (current !== entry || !Array.isArray(coordinates) || coordinates.length < 2) return;
            line.setLatLngs(coordinates.map(function (point) { return [point[1], point[0]]; }));
            if (data.distance != null && data.duration != null) {
                line.bindTooltip((route.distance / 1000).toFixed(1) + ' km · ' + Math.ceil(route.duration / 60) + ' min estimated travel time', { sticky: true });
            }
        }).catch(function () {
            if (routeCache.get(id) === entry) {
                routeLayer.removeLayer(line);
                routeCache.delete(id);
            }
        });
    }

    async function refresh() {
        var endpoint = window.deliveryConfig && window.deliveryConfig.mapStatusUrl;
        if (!endpoint || inFlight || document.visibilityState !== 'visible') return;
        inFlight = true;
        try {
            var response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Map update failed.');
            var data = await response.json();
            if (Array.isArray(data.liveLocations)) update(data.liveLocations, true);
        } catch (_) {
            var status = document.getElementById('deliveryMapStatus');
            if (status && !lastPoints.length) status.textContent = 'Live map is temporarily unavailable. Retrying automatically.';
        } finally {
            inFlight = false;
        }
    }

    function init() {
        var element = document.getElementById('liveDeliveryMap');
        if (!element || typeof L === 'undefined') {
            var loading = document.getElementById('deliveryMapLoading');
            if (loading) loading.textContent = 'Map library unavailable. Check the internet connection and reload.';
            return;
        }

        map = L.map(element, {
            scrollWheelZoom: false,
            minZoom: 12,
            maxBounds: santaCruzBounds,
            maxBoundsViscosity: 1,
        });
        var configuredStation = window.deliveryConfig && window.deliveryConfig.stationCoordinates;
        stationPoint = configuredStation
            ? validPoint(configuredStation.latitude, configuredStation.longitude)
            : null;
        if (!stationPoint) {
            map.setView(santaCruzCenter, 14);
        } else {
            map.setView(stationPoint, 15);
        }
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>',
        }).addTo(map);
        L.control.scale({ metric: true, imperial: false }).addTo(map);
        markers = L.layerGroup().addTo(map);
        routeLayer = L.layerGroup().addTo(map);

        var loading = document.getElementById('deliveryMapLoading');
        if (loading) loading.remove();
        update(window.deliveryMapInitial || []);
        window.deliveryLiveMap = { map: map, update: update };

        window.addEventListener('delivery-tab-change', function () {
            window.setTimeout(function () { map.invalidateSize(); }, 80);
        });
        window.addEventListener('resize', function () {
            if (map) {
                window.setTimeout(function () { map.invalidateSize(); }, 100);
            }
        });

        window.setInterval(refresh, 20000);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') refresh();
        });
        refresh();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
}());
