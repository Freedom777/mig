<?php

namespace App\Http\Controllers;

use App\File;
use App\FileInfo;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Intervention\Image\ImageManagerStatic as Image;
use Symfony\Component\Finder\SplFileInfo;

class FileController extends Controller
{

    protected $FS = null;

    public function __construct()
    {
        $this->FS = new Filesystem();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\File  $file
     * @return \Illuminate\Http\Response
     */
    public function show(File $file)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\File  $file
     * @return \Illuminate\Http\Response
     */
    public function edit(File $file)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\File  $file
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, File $file)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\File  $file
     * @return \Illuminate\Http\Response
     */
    public function destroy(File $file)
    {
        //
    }

    public function collect($path = 'D:\\', $diskLabel = 'local')
    {
        $path = 'C:\\333';
        $path = File::validatePath($path);

        if (!$path) {
            die('Invalid path');
        }

        // $container = Container::getInstance();
        $Finder = File::getFilesFinder($path);
        foreach ($Finder as $SplFileInfo) {
            /**
             * @var $FileInfo FileInfo
             * @var SplFileInfo $SplFileInfo
             */
            $FileInfo = new FileInfo($SplFileInfo->getPathname(), $SplFileInfo->getRelativePath(), $SplFileInfo->getRelativePathname());
            $imageExifAr = Image::make($FileInfo->getPathname())->exif();

            $FileInfo->setDiskLabel($diskLabel);
            $FileInfo->setImageWidth(isset($imageExifAr ['ExifImageWidth']) ? $imageExifAr ['ExifImageWidth'] : null);
            $FileInfo->setImageHeight(isset($imageExifAr ['ExifImageLength']) ? $imageExifAr ['ExifImageLength'] : null);
            $FileInfo->setImageOrientation(isset($imageExifAr ['Orientation']) ? $imageExifAr ['Orientation'] : null);
            $FileInfo->setImageCreatedAt(isset($imageExifAr ['DateTimeOriginal']) ? $imageExifAr ['DateTimeOriginal'] : null);

            array_walk_recursive( $imageExifAr, function (&$entry) {
                $entry = mb_convert_encoding($entry, 'UTF-8');
            });
            $FileInfo->setImageExif(json_encode($imageExifAr));

            File::saveJpeg($FileInfo);
        }

        die('Completed');
    }

    protected function recursiveGet($path) {
        $dirs = $this->FS->directories($path);
    }

}
