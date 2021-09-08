<?php

namespace Smindel\GIS\Control;

use DOMDocument;

class FluidXmlWriter
{
    protected $dom;
    protected $heap = [];

    // todo: create method for createTextNode() for proper string escaping

    public static function create()
    {
        return new self(...func_get_args());
    }

    public function __construct()
    {
        $this->push($this->dom = new DOMDocument(...func_get_args()));
    }

    public function push($item)
    {
        $this->current() && $this->current()->appendChild($item);
        $this->heap[] = $item;

        return $this;
    }

    public function pop(int $levels = 1)
    {
        for($i = 0; $i < $levels; $i++) array_pop($this->heap);

        return $this;
    }

    public function current()
    {
        return end($this->heap);
    }

    public function make($tag, $ns = null)
    {
        $this->push($ns
            ? $this->dom->createElementNS($ns, $tag)
            : $this->dom->createElement($tag)
        );

        return $this;
    }

    public function value($value)
    {
        $this->current()->nodeValue = $value;

        return $this;
    }

    public function attribute($name, $value)
    {
        $this->current()->setAttribute($name, $value);

        return $this;
    }

    public function attributes($map)
    {
        foreach($map as $name => $value) {
            $this->attribute($name, $value);
        }

        return $this;
    }

    public function toXml()
    {
        return $this->dom->saveXML();
    }
}
