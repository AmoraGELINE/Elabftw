<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Factories;

use Elabftw\Interfaces\MakeThumbnailInterface;
use Elabftw\Make\MakeNullThumbnail;
use Elabftw\Make\MakeThumbnail;
use Elabftw\Make\MakeThumbnailFromPdf;

/**
 * Get a thumbnail maker depending on the mime type
 */
class MakeThumbnailFactory
{
    /**
     * Do some sane white-listing. In theory, gmagick handles almost all image formats,
     * but the processing of rarely used formats may be less tested/stable or may have security issues
     * when adding new mime types take care of ambiguities:
     * e.g. image/eps may be a valid application/postscript; image/bmp may also be image/x-bmp or
     * image/x-ms-bmp
     */
    public static function getMaker(string $mime, string $filePath, string $longName): MakeThumbnailInterface
    {
        return match ($mime) {
            'application/pdf' => new MakeThumbnailFromPdf($mime, $filePath, $longName),
            'image/heic', 'image/png', 'image/jpeg', 'image/gif', 'image/tiff', 'image/x-eps', 'image/svg+xml','application/postscript' => new MakeThumbnail($mime, $filePath, $longName),
            default => new MakeNullThumbnail($mime, $filePath, $longName),
        };
    }
}
