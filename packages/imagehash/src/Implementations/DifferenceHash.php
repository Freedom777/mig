<?php namespace Jenssegers\ImageHash\Implementations;

use Intervention\Image\Image;
use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\Implementation;

class DifferenceHash implements Implementation
{
    protected int $size;

    public function __construct(int $size = 8)
    {
        $this->size = $size;
    }

    public function hash(Image $image): Hash
    {
        // For this implementation we create a 8x9 image.
        $width = $this->size + 1;
        $height = $this->size;

        // Resize the image.
        $resized = $image->resize($width, $height);

        $bits = [];
        for ($y = 0; $y < $height; $y++) {
            // Get the pixel value for the leftmost pixel.
            $colorCollection = $resized->colorsAt(0, $y);
            $color = $colorCollection->first();
            $rgb = [(int) $color->red(), (int) $color->green(), (int) $color->blue()];

            $left = (int) floor(($rgb[0] * 0.299) + ($rgb[1] * 0.587) + ($rgb[2] * 0.114));

            for ($x = 1; $x < $width; $x++) {
                // Get the pixel value for each pixel starting from position 1.
                $colorCollection = $resized->colorsAt($x, $y);
                $color = $colorCollection->first();
                $rgb = [(int) $color->red(), (int) $color->green(), (int) $color->blue()];
                $right = (int) floor(($rgb[0] * 0.299) + ($rgb[1] * 0.587) + ($rgb[2] * 0.114));

                // Each hash bit is set based on whether the left pixel is brighter than the right pixel.
                // http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
                $bits[] = (int) ($left > $right);

                // Prepare the next loop.
                $left = $right;
            }
        }

        return Hash::fromBits($bits);
    }
}
