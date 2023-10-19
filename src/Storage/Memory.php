<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Storage;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

/**
 * For in memory filesystem operations
 */
class Memory extends AbstractStorage
{
    public function getPath(): string
    {
        // compare to https://github.com/thephpleague/flysystem/issues/471#issuecomment-106231642
        return 'php://memory';
    }

    protected function getAdapter(): FilesystemAdapter
    {
        return new InMemoryFilesystemAdapter();
    }
}
