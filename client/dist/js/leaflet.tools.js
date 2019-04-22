// MouseCoords

L.Control.MouseCoords = L.Control.extend({
    onAdd: function(map) {
      var me = this;
      map.on('mousemove', function(e) { me.track(e); });
      this.div = L.DomUtil.create('div', 'leaflet-smartlayers-control');
      return this.div;
    },
    track: function(e) {
      this.div.innerHTML = e.latlng.lng.toFixed(4) + ', ' + e.latlng.lat.toFixed(4);
    }
});

L.control.mousecoords = function(opts) {
    return new L.Control.MouseCoords(opts);
}

// Watermark

L.Control.Watermark = L.Control.extend({
    onAdd: function(map) {

        var img = L.DomUtil.create('img');

        if ('image' in this.options) {
            img.src = this.options.image;
        } else {
            img.style.display = 'none';
        }
        if ('width' in this.options) {
            img.style.width = this.options.width;
        }
        if ('height' in this.options) {
            img.style.height = this.options.height;
        }

        return img;
    }
});

L.control.watermark = function(opts) {
    return new L.Control.Watermark(opts);
}

// Fullscreen

L.Control.Fullscreen = L.Control.extend({
    onAdd: function(map) {
        var me = this;
        this.div = L.DomUtil.create('div', 'leaflet-smartlayers-control');
        this.div.innerHTML = '⛶';
        this.div.addEventListener('click', function(e) { me.toggleFullscreen(e); });
        return this.div;
    },
    toggleFullscreen: function(e) {
        var element = document.body,
            requestMethod = element.requestFullScreen || element.webkitRequestFullScreen || element.mozRequestFullScreen || element.msRequestFullScreen;

        if (requestMethod) {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                requestMethod.call(element);
            }
        }
    }
});

L.control.fullscreen = function(opts) {
    return new L.Control.Fullscreen(opts);
}
