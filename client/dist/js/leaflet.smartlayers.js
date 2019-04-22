// Smart Layers

/*
- stop propagation of panel events to map
- wrap panel content to avoid collapse animation
- wrap panel content to archieve panel scrolling
 */

L.Control.SmartLayers = L.Class.extend({
    initialize: function(config) {
        defaults = {
            position: 'left',
            elements: []
        };
        this.config = L.Util.extend(defaults, config || {});
    },
    addTo: function(map) {
        var ls = this,
            wrapper = L.DomUtil.create(
                'div',
                'leaflet-smartlayers-wrapper',
                map.getContainer()
            ),
            panel = L.DomUtil.create(
                'div',
                'leaflet-smartlayers',
                wrapper
            );

        this.map = map;
        this.panel = panel;

        L.DomUtil.addClass(map.getContainer(), 'leaflet-smartlayers-' + this.config.position);

        L.DomEvent.disableClickPropagation(wrapper);
        L.DomEvent.disableScrollPropagation(wrapper);

        this.config.elements.forEach(function(elem){
            ls.addElement(elem);
        });
        L.DomUtil.toFront(panel);

        this.addToggle(map);
    },
    addToggle: function(map) {
        sl = this;
        (new (L.Control.extend({
            onAdd: function(map) {
                var me = this;
                this.map = map;
                this.div = L.DomUtil.create('div', 'leaflet-smartlayers-control');
                this.div.innerText = sl.config.position == 'left' ? '<' : '>';
                this.div.addEventListener('click', function(e) { me.toggleSmartLayers(e); });
                return this.div;
            },
            toggleSmartLayers: function(e) {
                if (L.DomUtil.hasClass(this.map.getContainer(), 'leaflet-smartlayers-collapsed')) {
                    L.DomUtil.removeClass(this.map.getContainer(), 'leaflet-smartlayers-collapsed');
                    this.div.innerText = sl.config.position == 'left' ? '<' : '>';
                } else {
                    L.DomUtil.addClass(this.map.getContainer(), 'leaflet-smartlayers-collapsed')
                    this.div.innerText = sl.config.position == 'left' ? '>' : '<';
                }
            }
        }))({position:'top' + this.config.position})).addTo(map)
    },
    addElement: function(elem){
        if (elem instanceof HTMLElement) {
            return this.panel.appendChild(elem);
        }
        if ('addToMap' in elem) {
            return this.panel.appendChild(elem.addToMap(this.map));
        }
    }
});

L.control.smartlayers = function(opts) {
    return new L.Control.SmartLayers(opts);
}

L.control.smartlayers.create = function(tagName, className, parent, content, attributes) {

    var element = L.DomUtil.create(tagName, className, parent);

    if (
        typeof HTMLElement === "object"
            ? content instanceof HTMLElement
            : content && typeof content === "object" && content !== null && content.nodeType === 1 && typeof content.nodeName === "string"
    ) {
        element.appendChild(content);
    } else if (
        typeof content === 'string'
        || content instanceof String
    ) {
        element.innerText = content;
    }

    if (attributes && typeof attributes === "object" && attributes !== null) {
        Object.keys(attributes).forEach(function(key){
            element.setAttribute(key, attributes[key]);
        })
    }

    return element;
}

L.control.smartlayers.Layers = function(layers, options) {
    this.data = layers;
    this.options = L.Util.extend({control: 'checkbox', transparencyControl: true, sortable: true, zIndexOffset: 0}, options || {})
}

L.control.smartlayers.Layers.prototype.addToMap = function(map) {

    var l = this, name = Math.random().toString(36).substr(2, 9),
        options = this.options, layers = this.data,
        container = L.control.smartlayers.create('div', 'leaflet-smartlayers-container');

    this.layers = container.layers = {};

    Object.keys(layers).forEach(function(key){
        var id = Math.random().toString(36).substr(2, 9),
            div = L.control.smartlayers.create('div', 'leaflet-smartlayers-layer', container, null, {id: id}),
            layerDefaults = {},
            layer = layers[key] instanceof L.Layer ? L.Util.extend(layerDefaults, {layer:layers[key]}) : L.Util.extend(layerDefaults, layers[key] || {}),
            control, attributes;

        layer.layer.id = id;
        layer.layer.div = div;
        l.layers[id] = layer;
        div.layer = layer;

        if (options.control) {
            attributes = {type: options.control, id: 'C' + id}

            if (options.control == 'radio') {
                attributes.name = name;
            }

            if (map.hasLayer(layer.layer)) {
                attributes.checked = 'checked';
            }

            control = L.control.smartlayers.create('input', null, div, null, attributes)
            control.layer = layers[key];

            div.label = L.control.smartlayers.create('label', null, div, key, {for: 'C' + id});

            layer.layer.on('add remove', function (e) {
                e.target.div.control.checked = e.type == 'add';
            });

            if (options.control == 'radio') {
                control.addEventListener('change', function(e){
                    Object.keys(l.layers).forEach(function(key){
                        if (l.layers[key].layer.div.control.checked) {
                            l.layers[key].layer.addTo(map);
                        } else {
                            map.removeLayer(l.layers[key].layer);
                        }
                    });
                });
            } else {
                control.addEventListener('change', function(e){
                    if (this.checked) {
                        layer.layer.addTo(map);
                    } else {
                        map.removeLayer(layer.layer);
                    }
                });
            }

            div.control = control;
        } else {
            div.label = L.control.smartlayers.create('div', null, div, key);
        }

        if (options.transparencyControl && options.control == 'checkbox') {
            attributes = {type: 'range', min:0, max: 1, step:.01, value:isNaN(layer.layer.opacity) ? 1 : layer.layer.opacity};
            control = L.control.smartlayers.create('input', null, div, null, attributes)
            control.layer = layers[key];
            control.draggable = true;
            control.addEventListener('dragstart', function(e){
                e.preventDefault();
                e.stopPropagation();
            });
            control.addEventListener('input', function(e){
                layer.layer.setOpacity(this.value);
            })

            div.slider = control;
        }
    });

    if (options.sortable && options.control == 'checkbox') {
        var updateOrder = function(container, layer) {
            var zIndex = container.childNodes.length + options.zIndexOffset;
            container.childNodes.forEach(function(child){
                if (child.layer.layer.setZIndex) {
                    child.layer.layer.setZIndex(zIndex--);
                }
            });
        }
        sortableContainer(container, updateOrder);
        updateOrder(container);
    }

    return container;
};

L.control.smartlayers.layers = function(layers, options) {
    return new L.control.smartlayers.Layers(layers, options);
};

function sortableContainer(container, fn) {

    var dragstart = function(e){
            e.stopPropagation();
            e.dataTransfer.setData("text/plain", e.target.id);
            e.dataTransfer.dropEffect = 'move';
        },
        dragover =  function(e){
            e.stopPropagation();
            var data = getDropData(e),
                zone = getDropZone(e.target)
            if (zone) {
                placeholder(zone, data);
                e.preventDefault();
            }
        },
        dragenter = function(e){
            e.stopPropagation();
            var zone = getDropZone(e.target),
                data = getDropData(e);
            if (zone) {
                placeholder(zone, data);
                L.DomUtil.addClass(zone, 'leaflet-smartlayers-drag');
            }
        },
        dragleave = function(e){
            e.stopPropagation();
            var zone = getDropZone(e.target);
            if (zone) {
                L.DomUtil.removeClass(zone, 'leaflet-smartlayers-drag');
            }
        },
        dragend =  function(e){
            e.stopPropagation();
            placeholder();
        },
        drop = function(e){
            e.preventDefault();
            e.stopPropagation();
            if (container.placeholder) {
                container.placeholder.parentNode.replaceChild(getDropData(e), container.placeholder);
                container.placeholder = null;
                e.preventDefault();
                e.stopPropagation();
                fn(container, getDropData(e));
            }
        },
        getDropData = function(e){
            return document.getElementById(e.dataTransfer.getData("text/plain"));
        },
        getDropZone = function(elem){
            while(elem && !elem.draggable){elem = elem.parentNode};
            return elem;
        },
        getIndex = function(elem){
            var index=false;
            if (!elem.parentNode) console.log(elem);
            elem.parentNode.childNodes.forEach(function(child,i){
                index = child === elem ? i : index;
            });
            return index;
        },
        placeholder = function(zone, data){
            if (!zone || !data || zone == data) {
                if (container.placeholder) {
                    container.placeholder.parentNode.removeChild(container.placeholder);
                    container.placeholder = null;
                }
            } else {
                container.placeholder = container.placeholder || data.cloneNode(true);
                if (!L.DomUtil.hasClass(container.placeholder)) {
                    L.DomUtil.addClass(container.placeholder, 'placeholder');
                    container.placeholder.id = 'plcaeholder';
                }

                if (getIndex(zone) < getIndex(data)) {
                    container.insertBefore(container.placeholder, zone);
                } else if (zone.nextSibling) {
                    container.insertBefore(container.placeholder, zone.nextSibling);
                } else {
                    container.appendChild(container.placeholder)
                }
            }
        };

    container.addEventListener('dragenter', dragenter);
    container.addEventListener('dragover', dragover);
    container.addEventListener('dragleave', dragleave);
    container.addEventListener('dragend', dragend);
    container.addEventListener('drop', drop);

    container.childNodes.forEach(function(child){
        child.draggable = true;
        child.addEventListener('dragstart', dragstart);
    });
}
