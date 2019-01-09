<?php

namespace App\Console\Commands;

use App\File;
use App\FileInfo;
use Illuminate\Console\Command;
use Intervention\Image\ImageManagerStatic as Image;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileOperation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'file:collect {path} {diskLabel=local}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // $request = Request::create($this->argument('uri'), 'GET');
        // $request = '/files';
        // $this->info(app()->make(\Illuminate\Contracts\Http\Kernel::class)->handle($request))

        $path = File::validatePath($this->argument('path'));

        if (!$path) {
            die('Invalid path');
        }

        $diskLabel = $this->argument('diskLabel');

        /**
         * @var Finder $Finder
         */
        $Finder = File::getFilesFinder($path);
        $Bar = $this->output->createProgressBar($Finder->count());

        $Bar->start();
        foreach ($Finder as $SplFileInfo) {
            /**
             * @var $FileInfo FileInfo
             * @var SplFileInfo $SplFileInfo
             */
            $FileInfo = new FileInfo($SplFileInfo->getPathname(), $SplFileInfo->getRelativePath(), $SplFileInfo->getRelativePathname());
            $imageExifAr = Image::make($FileInfo->getPathname())->exif();

            $dateTime = null;
            $FileInfo->setImageWidth(null);
            $FileInfo->setImageHeight(null);
            $FileInfo->setImageOrientation(null);
            $FileInfo->setImageExif('{}');

            if ( !empty($imageExifAr) ) {
                if (isset($imageExifAr ['DateTimeOriginal']) && File::validateDate($imageExifAr ['DateTimeOriginal'])) {
                    $dateTime = $imageExifAr ['DateTimeOriginal'];
                } elseif (!empty($imageExifAr ['FileDateTime'])) {
                    $dateTime = date('Y-m-d H:i:s', $imageExifAr ['FileDateTime']);
                }
                if (isset($imageExifAr ['ExifImageWidth'])) {
                    $FileInfo->setImageWidth($imageExifAr ['ExifImageWidth']);
                }
                if (isset($imageExifAr ['ExifImageLength'])) {
                    $FileInfo->setImageHeight($imageExifAr ['ExifImageLength']);
                }
                if (isset($imageExifAr ['Orientation'])) {
                    $FileInfo->setImageOrientation($imageExifAr ['Orientation']);
                }
                array_walk_recursive( $imageExifAr, function (&$entry) {
                    $entry = mb_convert_encoding($entry, 'UTF-8');
                });
                $FileInfo->setImageExif(json_encode($imageExifAr));
            }

            if (!File::validateDate($dateTime)) {
                $dateTime = date('Y-m-d H:i:s', $FileInfo->getMTime());
            }
            $FileInfo->setDiskLabel($diskLabel);
            $FileInfo->setImageCreatedAt($dateTime);

            File::saveJpeg($FileInfo);
            $Bar->advance();
        }

        $Bar->finish();
    }
}
