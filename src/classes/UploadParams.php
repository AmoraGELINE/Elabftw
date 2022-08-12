<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Services\Filter;

final class UploadParams extends ContentParams
{
    public function __construct(string $target, string $content, private ?\Symfony\Component\HttpFoundation\File\UploadedFile $file = null)
    {
        parent::__construct($target, $content);
    }

    public function getContent(): mixed
    {
        return match ($this->target) {
            'real_name' => $this->getRealName(),
            'comment' => Filter::title($this->content),
            'state' => $this->getInt(),
            'file' => $this->file,
            default => throw new ImproperActionException('Incorrect upload parameter.'),
        };
    }

    private function getRealName(): string
    {
        // don't allow php extension
        $ext = Tools::getExt($this->content);
        if ($ext === 'php') {
            throw new ImproperActionException('No php extension allowed!');
        }
        return Filter::forFilesystem($this->content);
    }
}
