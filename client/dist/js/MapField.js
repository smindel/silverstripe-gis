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
                var streets = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png').addTo(map);
                var satelite = L.tileLayer('http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
                    maxZoom: 20,
                    subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
                });

                var baseMaps = {
                    "Streets": streets,
                    "Satelite": satelite
                };

                var feature = this.getFeatureFromFormFieldValue();
                var drawnItems = new L.FeatureGroup([feature]);
                var drawControl = new L.Control.Draw({
                    draw: {
                        polyline: false,
                        circle: false,
                        rectangle: false,
                        circlemarker: false
                    },
                    edit: {
                        featureGroup: drawnItems
                    }
                });
                map.addLayer(drawnItems);
                map.addControl(drawControl);

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
                    parts = wkt.match(/^srid=(\d+);(point|polygon)\(([\d\.\s\(\),]+)\)/i),
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
                console.log(wkt, coordinates);

                return Array.isArray(coordinates[0][0])
                    ? L.polygon(coordinates[0])
                    : L.marker(coordinates[0]);
            },
            setFormFieldValueFromFeature: function(feature) {
                var srid = this.data('defaultSrid'), coords;

                if (!feature) {
                    return this.getFormField().val('');
                } else if (feature.getLatLng) {
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
                } else {
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
                }
                this.getFormField().val(wkt)
                console.log(latlngs);
                console.log(wkt);
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

        $('.map-field-widget.point-picker').entwine({
            onmatch: function() {

                var me = this,
                    coords = this.getCenter() || [0, 0];

                this.getFormField().attr('readonly', 'readonly');

                var map = L.map(this[0]).setView(coords, 13);

                var streets = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png').addTo(map);
                var satelite = L.tileLayer('http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
                    maxZoom: 20,
                    subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
                });

                var baseMaps = {
                    "Streets": streets,
                    "Satelite": satelite
                };

                var marker = L.marker(coords).addTo(map);

                var updateMarker = function(latlng) {
                    marker.setLatLng([latlng.lat, latlng.lng]);
                    return false;
                };

                L.control.layers(baseMaps).addTo(map);

                map.addControl(new L.Control.Search({
                    url: '//nominatim.openstreetmap.org/search?format=json&q={s}',
                    jsonpParam: 'json_callback',
                    propertyName: 'display_name',
                    propertyLoc: [
                        'lat', 'lon'
                    ],
                    marker: marker,
                    moveToLocation: function(latlng, title, map) {
                        me.setFieldValue([latlng.lat, latlng.lng]);
                        updateMarker(latlng);
                        map.flyTo(latlng);
                    },
                    autoCollapse: true,
                    autoType: false,
                    minLength: 2
                }));

                map.on('click', function(e) {
                    me.setFieldValue([e.latlng.lat, e.latlng.lng]);
                    updateMarker(e.latlng);
                });

                this.hover(function() {
                    var timer = setInterval(function() {
                        map.invalidateSize();
                    }, 5);
                    setTimeout(function() {
                        clearInterval(timer)
                    }, 1000);
                }, function() {
                    var timer = setInterval(function() {
                        map.invalidateSize()
                        me.center();
                    }, 5);
                    setTimeout(function() {
                        clearInterval(timer)
                    }, 1000);
                });

                this.setMap(map);
            },
            getCenter: function() {
                return this.getFieldValue()
            },
            getFieldValue: function() {
                var field = this.getFormField(),
                    fieldValue = field.val(),
                    location,
                    coords,
                    epsg,
                    proj;

                if (fieldValue) {
                    location = fieldValue.match(/^srid=(\d+);\w+\(([\d\.-\s]+)\)/i);
                    epsg = location[1];
                    coords = location[2].split(' ');
                    if (epsg != '4326') {
                        proj = proj4('EPSG:' + epsg);
                        coords = proj.inverse([
                            coords[1], coords[0]
                        ]);
                    }
                    return [
                        coords[1], coords[0]
                    ];
                }
            },
            setFieldValue: function(val) {
                var field = this.getFormField(),
                    fieldValue = field.val(),
                    coords,
                    matches;

                matches = fieldValue.match(/^srid=(\d+);/i);
                if (matches[1] != '4326') {
                    proj = proj4('EPSG:' + matches[1]);
                    val = proj.forward([
                        val[1], val[0]
                    ]);
                }

                fieldValue = fieldValue.replace(/\w+\([\d\.-\s]*\)/, 'POINT(' + val[1] + ' ' + val[0] + ')');
                field.val(fieldValue);
            }
        });

        $('.map-field-widget.polygon-editor').entwine({
            onmatch: function() {

                var me = this;

                this.getFormField().attr('readonly', 'readonly');

                var map = L.map(this[0]).setView([
                    0, 0
                ], 13);

                var polygon = L.polygon(this.getFieldValue());

                var drawnItems = new L.FeatureGroup([polygon]);
                map.addLayer(drawnItems);
                var drawControl = new L.Control.Draw({
                    draw: {
                        polyline: false,
                        circle: false,
                        rectangle: false,
                        circlemarker: false
                    },
                    edit: {
                        featureGroup: drawnItems
                    }
                });
                map.addControl(drawControl);
                map.on('draw:created', function(e) {
                    drawnItems.clearLayers().addLayer(e.layer);
                    if (e.layerType == 'marker') {}
                    me.setFieldValue(e.layer);
                }).on('draw:edited', function(e) {
                    console.log(e);
                    console.log(e.layers);
                    e.layers.eachLayer(function(layer) {
                        drawnItems.clearLayers().addLayer(layer);
                        me.setFieldValue(layer);
                    });
                }).on('draw:deleted', function(e) {
                    me.setFieldValue();
                });

                map.addControl(new L.Control.Search({
                    url: '//nominatim.openstreetmap.org/search?format=json&q={s}',
                    jsonpParam: 'json_callback',
                    propertyName: 'display_name',
                    propertyLoc: [
                        'lat', 'lon'
                    ],
                    marker: false,
                    autoCollapse: true,
                    autoType: false,
                    minLength: 2
                }));

                map.fitBounds(polygon.getBounds());

                L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png').addTo(map);

                this.hover(function() {
                    var timer = setInterval(function() {
                        map.invalidateSize()
                    }, 5);
                    setTimeout(function() {
                        clearInterval(timer)
                    }, 1000);
                }, function() {
                    var timer = setInterval(function() {
                        map.invalidateSize()
                    }, 5);
                    setTimeout(function() {
                        clearInterval(timer)
                    }, 1000);
                });

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
            wkt2polygon: function(wkt) {

                var parts = wkt.match(/srid=(\d+);(point|linestring|polygon|multipolygon)\s*\(([\d\s\.\(\),]*)\)/i),
                    epsg, proj, type;

                if (!parts) {
                    console.log(parts,wkt);
                    return;
                }

                epsg = parts[1];
                proj = epsg != '4326'
                    ? proj4('EPSG:' + epsg)
                    : false;
                type = parts[2].charAt(0).toUpperCase() + parts[2].slice(1).toLowerCase();

                var coordinates = '[' + parts[3].replace(/([\d\.-]+)\s+([\d\.-]+)/g, function(p, c1, c2) {
                    if (epsg != '4326') {
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

                return JSON.parse(coordinates);
            },
            setFieldValue: function(layer) {
                console.log(layer);
                var field = this.getFormField(),
                    fieldValue = field.val(),
                    latlngs = layer.getLatLngs()[0],
                    matches = fieldValue.match(/^srid=(\d+);/i),
                    epsg = matches[1],
                    proj = epsg != '4326'
                        ? proj4('EPSG:' + matches[1])
                        : false,
                    fieldFlue;

                fieldValue = 'SRID=' + epsg + ';POLYGON((';
                for (var i = 0; i < latlngs.length; i++) {
                    if (epsg != '4326') {
                        latlngs[i] = proj.forward([
                            latlngs[i].lng,
                            latlngs[i].lat
                        ]);
                        fieldValue += Math.round(latlngs[i][0]) + ' ' + Math.round(latlngs[i][1]);
                    } else {
                        fieldValue += Math.round(latlngs[i].lat) + ' ' + Math.round(latlngs[i].lng);
                    }
                    if (i < latlngs.length - 1) {
                        fieldValue += ',';
                    }
                }
                fieldValue += '))';
                field.val(fieldValue);
                console.log(fieldValue);
            }
        });

    });
});
