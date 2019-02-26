<?php

namespace Smindel\GIS\Tests;

trait RenderingAssertion
{
    public function assertRenders($imageBinary, $x, $y, $color = null, $msg = null)
    {
        if ($color === null) {
            $image = imagecreatefromstring($imageBinary);
            $this->assertNotEquals(0, imagecolorat($image, $x, $y), $msg);
        } else {
            $this->assertEquals($color, $this->getColorAt($imageBinary, $x, $y), $msg);
        }
    }

    public function assertNotRenders($imageBinary, $x, $y, $color = [], $msg = null)
    {
        if ($color === null) {
            $image = imagecreatefromstring($imageBinary);
            $this->assertEquals(0, imagecolorat($image, $x, $y), $msg);
        } else {
            $this->assertNotEquals($color, $this->getColorAt($imageBinary, $x, $y), $msg);
        }
    }

    protected function getColorAt($imageBinary, $x, $y)
    {
        $image = imagecreatefromstring($imageBinary);

        // file_put_contents('public/assets/tile.png', $imageBinary);

        if (imageistruecolor($image)) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            return [$r, $g, $b];
        } else {
            return imagecolorsforindex($image, imagecolorat($image, $x, $y));
        }
    }
}
