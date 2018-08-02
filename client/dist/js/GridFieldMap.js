jQuery(function($) {
    $.entwine('ss', function($) {
      $('.grid-field-map').entwine({
        onmatch: function() {

            var me = this,
                gridFieldUrl = this.closest('.ss-gridfield').data('url'),
                value = $(this).data('mapCenter'),
                coords = value.match(/^srid=(\d+);\w+\(([\d\.-\s]+)\)/i),
                epsg = coords[1],
                center = coords[2].split(' '),
                proj;

            if (epsg != '4326') {
                proj = proj4('EPSG:' + epsg);
                center = proj.inverse([center[1], center[0]]);
            }
            center = [center[1], center[0]];

            this.css({width:'100%', height:'150px', transition:'1s height'});

            var map = L.map(this[0]).setView(center, 13);

            L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            L.geoJson.ajax('giswebservice/Catalyst-SilverGeo-Model-Location.GeoJson')
              .bindPopup(function (layer) {
                return '<a href="' + gridFieldUrl + '/item/' + layer.feature.properties.ID + '">' + layer.feature.properties.Title + '</a>';
              })
              .addTo(map)
              .on('data:loaded', function(){
                map.fitBounds(this.getBounds());
              });

            map.addControl(new L.Control.Search({
              url: '//nominatim.openstreetmap.org/search?format=json&q={s}',
              jsonpParam: 'json_callback',
              propertyName: 'display_name',
              propertyLoc: ['lat','lon'],
              marker: false,
              autoCollapse: true,
              autoType: false,
              minLength: 2
            }));

            this.hover(
                function(){ $(this).css({height:'400px'});var timer = setInterval(function(){map.invalidateSize()},5); setTimeout(function(){clearInterval(timer)}, 1000); },
                function(){ $(this).css({height:'150px'});var timer = setInterval(function(){map.invalidateSize()},5); setTimeout(function(){clearInterval(timer)}, 1000); }
            );
        }
      });
    });
});
