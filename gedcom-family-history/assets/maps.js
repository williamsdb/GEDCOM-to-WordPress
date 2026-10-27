(function () {
    'use strict';
    document.querySelectorAll('.gfh-map-wrap').forEach(function (wrapper) {
        var button = wrapper.querySelector('.gfh-load-map');
        var canvas = wrapper.querySelector('.gfh-map');
        var status = wrapper.querySelector('.gfh-map-status');
        button.addEventListener('click', function () {
            if (!window.L) { status.textContent = 'The map could not load. The places and events are available in the list below.'; return; }
            var points;
            try { points = JSON.parse(wrapper.dataset.gfhPoints); } catch (e) { status.textContent = 'The map data could not be read.'; return; }
            canvas.hidden = false;
            var map = L.map(canvas, {scrollWheelZoom: false});
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
            }).on('tileerror', function () { status.textContent = 'Some map tiles could not load. Pins and the text list remain available.'; }).addTo(map);
            var bounds = [];
            points.forEach(function (point, index) {
                var popup = document.createElement('div');
                point.events.forEach(function (event) {
                    var block = document.createElement('p');
                    var heading = document.createElement('strong'); heading.textContent = event.label;
                    block.appendChild(heading);
                    [event.date, event.detail].concat(event.location || [event.place], [event.accuracy]).filter(Boolean).forEach(function (text) {
                        block.appendChild(document.createElement('br')); block.appendChild(document.createTextNode(text));
                    });
                    popup.appendChild(block);
                });
                var marker = L.marker([point.lat, point.lng], {
                    icon: L.divIcon({className: 'gfh-map-pin', html: String(index + 1), iconSize: [30,30], iconAnchor: [15,15]}),
                    title: point.events[0].place, alt: 'Place ' + (index + 1) + ': ' + point.events[0].place,
                    keyboard: true
                }).addTo(map).bindPopup(popup);
                bounds.push(marker.getLatLng());
            });
            if (bounds.length === 1) { map.setView(bounds[0], 11); }
            else { map.fitBounds(bounds, {padding:[35,35], maxZoom:13}); }
            button.hidden = true;
            canvas.tabIndex = 0;
            canvas.focus();
        });
    });
})();
