jQuery(function($) {
    $.entwine('ss', function($) {
      $('.grid-field-map').entwine({
        onmatch: function() {

            var me = this,
                value = $(this).data('mapCenter')
                coords = value.match(/\w+\(([\d\.-\s]+)\)/)[1].split(' '),
                center = [coords[1], coords[0]];

            this.css({width:'100%', height:'150px', transition:'1s height'});

            var map = L.map(this[0]).setView(center, 13);

            L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // var marker = L.marker(center).addTo(map);

            this.hover(
                function(){ $(this).css({height:'400px'});var timer = setInterval(function(){map.invalidateSize()},5); setTimeout(function(){clearInterval(timer)}, 1000); },
                function(){ $(this).css({height:'150px'});var timer = setInterval(function(){map.invalidateSize()},5); setTimeout(function(){clearInterval(timer)}, 1000); }
            );
        }
      });
    });
});
