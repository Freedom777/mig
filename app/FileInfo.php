<?php
/**
 * Created by PhpStorm.
 * User: Oleg
 * Date: 07.01.2019
 * Time: 19:31
 */

namespace App;

class FileInfo extends \Symfony\Component\Finder\SplFileInfo {
    protected $diskLabel = null;
    protected $imageWidth = null;
    protected $imageHeight = null;
    protected $imageOrientation = null;
    protected $imageCreatedAt = null;
    protected $imageExif = null;

    /**
     * @return string|null
     */
    public function getDiskLabel()
    {
        return $this->diskLabel;
    }

    /**
     * @param string|null $diskLabel
     */
    public function setDiskLabel($diskLabel): void
    {
        $this->diskLabel = $diskLabel;
    }

    /**
     * @return null
     */
    public function getImageWidth()
    {
        return $this->imageWidth;
    }

    /**
     * @param null $imageWidth
     */
    public function setImageWidth($imageWidth): void
    {
        $this->imageWidth = $imageWidth;
    }

    /**
     * @return null
     */
    public function getImageHeight()
    {
        return $this->imageHeight;
    }

    /**
     * @param null $imageHeight
     */
    public function setImageHeight($imageHeight): void
    {
        $this->imageHeight = $imageHeight;
    }

    /**
     * @return null
     */
    public function getImageOrientation()
    {
        return $this->imageOrientation;
    }

    /**
     * @param null $imageOrientation
     */
    public function setImageOrientation($imageOrientation): void
    {
        $this->imageOrientation = $imageOrientation;
    }

    /**
     * @return null
     */
    public function getImageCreatedAt()
    {
        return $this->imageCreatedAt;
    }

    /**
     * @param null $imageCreatedAt
     */
    public function setImageCreatedAt($imageCreatedAt): void
    {
        $this->imageCreatedAt = $imageCreatedAt;
    }

    /**
     * @return null
     */
    public function getImageExif()
    {
        return $this->imageExif;
    }

    /**
     * @param null $imageExif
     */
    public function setImageExif($imageExif): void
    {
        $this->imageExif = $imageExif;
    }

}