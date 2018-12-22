<?php
/**
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use Elabftw\Exceptions\FilesystemErrorException;
use Gmagick;
use Exception;

/**
 * Create a thumbnail from a file
 */
final class MakeThumbnail
{
    /** @var int BIG_FILE_THRESHOLD size of a file in bytes above which we don't process it (5 Mb) */
    private const BIG_FILE_THRESHOLD = 5000000;

    /** @var int WIDTH the width for the thumbnail */
    private const WIDTH = 100;

    /**
     * Do some sane white-listing. In theory, gmagick handles almost all image formats,
     * but the processing of rarely used formats may be less tested/stable or may have security issues
     * when adding new mime types take care of ambiguities:
     * e.g. image/eps may be a valid application/postscript; image/bmp may also be image/x-bmp or
     * image/x-ms-bmp
     * @var array GMAGICK_WHITELIST
     */
    private const GMAGICK_WHITELIST = array(
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/tiff',
        'image/x-eps',
        'image/svg+xml',
        'application/pdf',
        'application/postscript'
    );

    /** @var string $filePath full path to file */
    private $filePath;

    /** @var string $thumbPath full path to thumbnail */
    private $thumbPath;

    /** @var string $mime mime type of the file */
    private $mime;

    /**
     * This class has no public method. Just instance it with a filePath and it creates the thumbnail if needed.
     *
     * @param string $filePath the full path to the file
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        // get mime type of the file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $this->mime = finfo_file($finfo, $this->filePath);
        $this->thumbPath = $this->filePath . '_th.jpg';

        if ($this->checkFile()) {
            $this->makeThumb();
        }
    }

    /**
     * Check if the file is readable and not too big, or if thumb exists already
     *
     * @return bool
     */
    private function checkFile(): bool
    {
        if (\is_readable($this->filePath) === false) {
            throw new FilesystemErrorException("File not found! (" . \substr($this->filePath, 0, 42) . "…)");
        }

        if (filesize($this->filePath) > self::BIG_FILE_THRESHOLD || \file_exists($this->thumbPath)) {
            return false;
        }
        return true;
    }

    /**
     * Create a thumbnail with Gmagick extension
     *
     * @return void
     */
    private function useGmagick(): void
    {
        if (!\in_array($this->mime, self::GMAGICK_WHITELIST, true)) {
            return;
        }

        // if pdf or postscript, generate thumbnail using the first page (index 0) do the same for postscript files
        // sometimes eps images will be identified as application/postscript as well, but thumbnail generation still
        // works in those cases
        if ($mime === 'application/pdf' || $mime === 'application/postscript') {
            $this->filePath .= '[0]';
        }
        // fail silently if thumbnail generation does not work to keep file upload field functional
        // originally introduced due to issue #415.
        try {
            $image = new Gmagick($this->filePath);
        } catch (Exception $e) {
            return;
        }
        // create thumbnail of width 100px; height is calculated automatically to keep the aspect ratio
        $image->thumbnailimage(self::WIDTH, 0);
        // create the physical thumbnail image to its destination (85% quality)
        $image->setCompressionQuality(85);
        $image->write($this->thumbPath);
        $image->clear();
    }

    /**
     * Create a thumbnail with GD extension
     *
     * @return void
     */
    private function useGd(): void
    {
        // the fonction used is different depending on extension
        switch ($this->mime) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($this->filePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($this->filePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($this->filePath);
                break;
            default:
                return;
        }

        // something went wrong
        if ($sourceImage === false) {
            return;
        }

        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);

        // find the "desired height" of this thumbnail, relative to the desired width
        $desiredHeight = (int) floor($height * (self::WIDTH / $width));

        // create a new, "virtual" image
        $virtualImage = imagecreatetruecolor(self::WIDTH, $desiredHeight);
        if ($virtualImage === false) {
            return;
        }

        // copy source image at a resized size
        imagecopyresized($virtualImage, $sourceImage, 0, 0, 0, 0, self::WIDTH, $desiredHeight, $width, $height);

        // create the physical thumbnail image to its destination (85% quality)
        imagejpeg($virtualImage, $this->thumbPath, 85);
    }

    /**
     * Create a jpg thumbnail from images of type jpeg, png, gif, tiff, eps and pdf.
     *
     * @return void
     */
    private function makeThumb(): void
    {
        // use gmagick preferentially
        if (\extension_loaded('gmagick')) {
            $this->useGmagick();

        // if we don't have gmagick, try with gd
        } elseif (extension_loaded('gd')) {
            $this->useGd();
        }
    }
}
