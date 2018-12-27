// TODO: edit marker is broken

jQuery(function($) {
    $.entwine('ss', function($) {

        $('.map-field-widget').entwine({
            Map: null,
            Feature: null,
            onmatch: function() {

                this.getFormField().attr('readonly', 'readonly');

                var me = this;

                var map = L.map(this[0], { worldCopyJump: true, maxBoundsViscosity: 1.0 });
                var streets = L.tileLayer('//{s}.tile.osm.org/{z}/{x}/{y}.png').addTo(map);
                var satelite = L.tileLayer('//{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
                    maxZoom: 20,
                    subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
                });

                var baseMaps = {
                    "Streets": streets,
                    "Satelite": satelite
                };

                var feature = this.getFeatureFromFormFieldValue();
                var drawnItems = new L.FeatureGroup([feature]);

                if (!this.getFormField().hasClass('mapfield_readonly')) {
                    var drawControl = new L.Control.Draw({
                        draw: this.data('controls'),
                        edit: {
                            featureGroup: drawnItems
                        }
                    });
                    map.addControl(drawControl);
                }
                map.addLayer(drawnItems);

                map.addControl(new L.Control.Search({
                    url: '//nominatim.openstreetmap.org/search?format=json&q={s}',
                    jsonpParam: 'json_callback',
                    propertyName: 'display_name',
                    propertyLoc: [
                        'lat', 'lon'
                    ],
                    marker: false,
                    // moveToLocation: function(latlng, title, map) {
                    //     me.setFieldValue([latlng.lat, latlng.lng]);
                    //     updateMarker(latlng);
                    //     map.flyTo(latlng);
                    // },
                    autoCollapse: true,
                    autoType: false,
                    minLength: 2
                }));

                L.control.layers(baseMaps).addTo(map);

                map.on('draw:created', function(e) {
                    drawnItems.clearLayers().addLayer(e.layer);
                    if (e.layerType == 'marker') {}
                    me.setFormFieldValueFromFeature(e.layer);
                    me.setFeature(e.layer);
                }).on('draw:edited', function(e) {
                    e.layers.eachLayer(function(layer) {
                        // drawnItems.clearLayers().addLayer(layer);
                        me.setFormFieldValueFromFeature(layer);
                        me.setFeature(layer);
                    });
                }).on('draw:deleted', function(e) {
                    me.setFormFieldValueFromFeature();
                    me.setFeature(null);
                });

                this.setMap(map).setFeature(feature);

                if (feature.getBounds) {
                    map.fitBounds(feature.getBounds());
                } else if (feature.getLatLng) {
                    map.setView(feature.getLatLng(), 13);
                }
            },
            getFormField: function() {
                return $('#' + $(this).data('field'));
            },
            getFeatureFromFormFieldValue: function() {
                var wkt = this.getFormField().val(),
                    parts = wkt.match(/^srid=(\d+);(point|linestring|polygon|multipolygon)\(([-\d\.\s\(\),]+)\)/i),
                    srid, proj, type, json, coordinates;

                if (!parts) return null;

                srid = parts[1];
                proj = srid != '4326'
                    ? proj4('EPSG:' + srid)
                    : false;
                type = parts[2].toUpperCase();

                json = '[' + parts[3].replace(/([\d\.-]+)\s+([\d\.-]+)/g, function(p, c1, c2) {
                    if (srid != '4326') {
                        coords = proj.inverse([c1, c2]);
                        return '[' + coords[1] + ',' + coords[0] + ']';
                    } else {
                        return '[' + c2 + ',' + c1 + ']';
                    }
                }).replace(/([\(\)])/g, function(p, c) {
                    return c == '('
                        ? '['
                        : ']';
                }) + ']';

                coordinates = JSON.parse(json);

                switch (type) {
                    case 'POINT': return L.marker(coordinates[0]);
                    case 'LINESTRING': return L.polyline(coordinates);
                    case 'POLYGON': return L.polygon(coordinates[0]);
                    case 'MULTIPOLYGON': return L.polygon(coordinates);
                }
            },
            setFormFieldValueFromFeature: function(feature) {
                var srid = this.data('defaultSrid'), coords;

                if (feature instanceof L.Marker) {
                    var latlngs = feature.getLatLng();
                    var wkt = 'SRID=' + srid + ';POINT(';
                    if (srid != '4326') {
                        coords = proj4('EPSG:' + srid).forward([
                            latlngs.lng,
                            latlngs.lat
                        ]);
                        wkt += coords[0] + ' ' + coords[1];
                    } else {
                        wkt += latlngs.lng + ' ' + latlngs.lat;
                    }
                    wkt += ')';
                } else if (feature instanceof L.Polygon) {
                    var latlngs = feature.getLatLngs()[0];
                    var wkt = 'SRID=' + srid + ';POLYGON((';
                    latlngs.push(latlngs[0]);
                    for (var i = 0; i < latlngs.length; i++) {
                        if (srid != '4326') {
                            coords = proj4('EPSG:' + srid).forward([
                                latlngs[i].lng,
                                latlngs[i].lat
                            ]);
                            wkt += coords[0] + ' ' + coords[1];
                        } else {
                            wkt += latlngs[i].lng + ' ' + latlngs[i].lat;
                        }
                        if (i < latlngs.length - 1) wkt += ',';
                    }
                    wkt += '))';
                } else if (feature instanceof L.Polyline) {
                    var latlngs = feature.getLatLngs();
                    var wkt = 'SRID=' + srid + ';LINESTRING(';
                    for (var i = 0; i < latlngs.length; i++) {
                        if (srid != '4326') {
                            coords = proj4('EPSG:' + srid).forward([
                                latlngs[i].lng,
                                latlngs[i].lat
                            ]);
                            wkt += coords[0] + ' ' + coords[1];
                        } else {
                            wkt += latlngs[i].lng + ' ' + latlngs[i].lat;
                        }
                        if (i < latlngs.length - 1) wkt += ',';
                    }
                    wkt += ')';
                } else {
                    return this.getFormField().val('');
                }
                this.getFormField().val(wkt)
            },
            getCenter: function () {
                var feature = this.getFeature();
                if (feature) return feature.getCenter ? feature.getCenter() : feature.getLatLng();
            },
            center: function() {
                var latlng = this.getCenter();
                this.getMap().flyTo(latlng);
            },
            onmouseover: function() {
                var map = this.getMap(), timer = setInterval(function() {
                    map.invalidateSize();
                }, 5);
                setTimeout(function() {
                    clearInterval(timer)
                }, 1000);
            },
            onmouseout: function() {
                var me = this, map = this.getMap(), timer = setInterval(function() {
                    map.invalidateSize()
                }, 5);
                setTimeout(function() {
                    clearInterval(timer)
                    me.center();
                }, 1000);
            }
        });

    });
});
