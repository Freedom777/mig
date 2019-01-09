<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;

/**
 * @property int $id
 * @property string $disk_label
 * @property string $path
 * @property string $filename
 * @property string $thumb
 * @property string $new_filename
 * @property string $sha_checksum
 * @property int $size
 * @property int $width
 * @property int $height
 * @property int $exif_orientation
 * @property string $exif_created_at
 * @property string $exif_full_data
 * @property int $status
 * @property string $created_at
 * @property string $updated_at
 */
class File extends Model
{
    /**
     * @var array
     */
    protected $fillable = ['disk_label', 'path', 'filename', 'thumb', 'new_filename', 'sha_checksum', 'size', 'width', 'height', 'exif_orientation', 'exif_created_at', 'exif_full_data', 'status'];

    const STATUS_SCANNED = 0;

    public static function getFilesFinder($path, $extPattern = ['*.jpg', '*.JPG']) {
        return Finder::create()
            ->files()
            ->name($extPattern)
            ->ignoreUnreadableDirs()
            ->in($path)
            ->notPath('$RECYCLE.BIN');
    }

    /**
     * @param FileInfo $FileInfo
     * @return bool
     */
    public static function saveJpeg(FileInfo $FileInfo) {
        // $File = new self();
        $File = File::where([
            'disk_label' => $FileInfo->getDiskLabel(),
            'path' => $FileInfo->getRelativePath(),
            'filename' => $FileInfo->getFilename()
        ])->first();

        if (!$File) {
            $File = new File();
            $File->path = $FileInfo->getRelativePath();
            $File->filename = $FileInfo->getFilename();
            $File->size = $FileInfo->getSize();
            $File->sha_checksum = sha1($FileInfo->getContents());
            $File->disk_label = $FileInfo->getDiskLabel();
            $File->width = $FileInfo->getImageWidth();
            $File->height = $FileInfo->getImageHeight();
            $File->exif_orientation = $FileInfo->getImageOrientation();
            $File->exif_created_at = $FileInfo->getImageCreatedAt();
            $File->exif_full_data = $FileInfo->getImageExif();
            return $File->save();
        }

        return true;
    }

    /**
     * @param $path
     * @return bool|string
     */
    public static function validatePath($path) {
        $FS = new Filesystem();

        if (!$FS->isDirectory($path)) {
            return false;
        }

        // Add directory separator if needed to next sequences
        if ( substr($path, -1) != DIRECTORY_SEPARATOR ) {
            $path .= DIRECTORY_SEPARATOR;
        }

        return $path;
    }

    public static function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        if (empty($date)) {
            return false;
        }
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    /**
     * Cast an object into a different class.
     *
     * Currently this only supports casting DOWN the inheritance chain,
     * that is, an object may only be cast into a class if that class
     * is a descendant of the object's current class.
     *
     * This is mostly to avoid potentially losing data by casting across
     * incompatable classes.
     *
     * @param object $object The object to cast.
     * @param string $class The class to cast the object into.
     * @return object
     */
    public static function cast($object, $class) {
        if( !is_object($object) )
            throw new InvalidArgumentException('$object must be an object.');
        if( !is_string($class) )
            throw new InvalidArgumentException('$class must be a string.');
        if( !class_exists($class) )
            throw new InvalidArgumentException(sprintf('Unknown class: %s.', $class));
        if( !is_subclass_of($class, get_class($object)) )
            throw new InvalidArgumentException(sprintf(
                '%s is not a descendant of $object class: %s.',
                $class, get_class($object)
            ));
        /**
         * This is a beautifully ugly hack.
         *
         * First, we serialize our object, which turns it into a string, allowing
         * us to muck about with it using standard string manipulation methods.
         *
         * Then, we use preg_replace to change it's defined type to the class
         * we're casting it to, and then serialize the string back into an
         * object.
         */
        return unserialize(
            preg_replace(
                '/^O:\d+:"[^"]++"/',
                'O:'.strlen($class).':"'.$class.'"',
                serialize($object)
            )
        );
    }

}
