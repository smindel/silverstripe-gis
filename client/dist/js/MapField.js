jQuery(function($) {
    $.entwine('ss', function($) {

        $('.map-field-widget').entwine({
            Map: null,
            getFormField: function () {
                var fieldName = $(this).data('field');
                return $('#' + fieldName);
            },
            onmouseout: function() {
                this.getMap().flyTo(this.getCenter());
            },
            wkt2polygon: function(wkt) {

                var parts = wkt.match(/\b(point|linestring|polygon|multipolygon)\s*\((.*)\)/i),
                    type = parts[1].charAt(0).toUpperCase() + parts[1].slice(1).toLowerCase();

                var coordinates = '[' + parts[2]
                    .replace(/([\d\.-]+)\s+([\d\.-]+)/g, function(p,c1,c2) {
                        return '[' + c2 + ',' + c1 + ']';
                    }).replace(/([\(\)])/g, function(p,c) {
                        return c == '(' ? '[' : ']';
                    }) + ']';

                return JSON.parse(coordinates);
            }
        });

        $('.map-field-widget.point-picker').entwine({
            onmatch: function() {

                var me = this,
                    coords = this.getCenter() || [0,0];

                this.getFormField().attr('readonly', 'readonly');

                var map = L.map(this[0]).setView(coords, 13);

                var layer = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png').addTo(map);

                var marker = L.marker(coords).addTo(map);

                var updateMarker = function(latlng) {
                    marker.setLatLng([latlng.lat, latlng.lng]);
                    return false;
                };
                map.on('click', function(e) {
                    me.setFieldValue([e.latlng.lat,e.latlng.lng]);
                    updateMarker(e.latlng);
                });

                this.hover(
                    function(){ var timer = setInterval(function(){map.invalidateSize()},5); setTimeout(function(){clearInterval(timer)}, 1000); },
                    function(){ var timer = setInterval(function(){map.invalidateSize()},5); setTimeout(function(){clearInterval(timer)}, 1000); }
                );

                this.setMap(map);
            },
            getCenter: function () {
                return this.getFieldValue()
            },
            getFieldValue: function() {
                var field = this.getFormField(),
                    fieldValue = field.val(),
                    coords;

                if (fieldValue) {
                    coords = fieldValue.match(/\w+\(([\d\.-\s]+)\)/)[1].split(' ');
                    return [coords[1], coords[0]];
                }
            },
            setFieldValue: function(val) {
                var field = this.getFormField(),
                    fieldValue = field.val(),
                    coords;

                fieldValue = fieldValue.replace(/\w+\([\d\.-\s]*\)/, 'POINT(' + val[1] + ' ' + val[0] + ')');
                field.val(fieldValue);
            }
        });

        $('.map-field-widget.polygon-editor').entwine({
            onmatch: function() {

                this.getFormField().attr('readonly', 'readonly');

                var map = L.map(this[0]).setView([0,0], 13);

                L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png').addTo(map);

                var polygon = L.polygon(this.getFieldValue()).addTo(map);

                map.fitBounds(polygon.getBounds());

                this.hover(
                    function(){ var timer = setInterval(function(){map.invalidateSize()},5); setTimeout(function(){clearInterval(timer)}, 1000); },
                    function(){ var timer = setInterval(function(){map.invalidateSize()},5); setTimeout(function(){clearInterval(timer)}, 1000); }
                );

                this.setMap(map);
            },
            onmouseout: function() {
                this.getMap().fitBounds(L.polygon(this.getFieldValue()).getBounds());
            },
            getFieldValue: function() {
                var field = this.getFormField(),
                    fieldValue = field.val();

                if (fieldValue) {
                    return this.wkt2polygon(fieldValue);
                }
            },
        });

    });
});
