<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\AbstractEntity;
use Elabftw\Models\Config;
use Elabftw\Models\Experiments;
use Elabftw\Models\Items;
use ZipStream\Option\Archive as ArchiveOptions;
use ZipStream\ZipStream;

/**
 * Make a zip with only the modified items on a time period
 */
class MakeBackupZip extends AbstractMake
{
    private ZipStream $Zip;

    private string $folder = '';

    /**
     * Give me a time period, I make good zip for you
     */
    public function __construct(AbstractEntity $entity, private string $period)
    {
        parent::__construct($entity);

        // we check first if the zip extension is here
        if (!class_exists('ZipArchive')) {
            throw new ImproperActionException('Fatal error! Missing extension: php-zip. Make sure it is installed and activated.');
        }

        $opt = new ArchiveOptions();
        $opt->setFlushOutput(true);
        $this->Zip = new ZipStream(null, $opt);
    }

    /**
     * Get the name of the generated file
     */
    public function getFileName(): string
    {
        return 'export.elabftw.zip';
    }

    /**
     * Loop on each id and add it to our zip archive
     * This could be called the main function.
     */
    public function getZip(): void
    {
        // loop on every user
        $usersArr = $this->Entity->Users->readFromQuery('');
        foreach ($usersArr as $user) {
            $idArr = $this->Entity->getIdFromLastchange((int) $user['userid'], $this->period);
            foreach ($idArr as $id) {
                $this->addToZip((int) $id, $user['fullname']);
            }
        }
        $this->Zip->finish();
    }

    /**
     * Folder and zip file name begins with date for experiments
     */
    private function getBaseFileName(): string
    {
        if ($this->Entity instanceof Experiments) {
            return $this->Entity->entityData['date'] . ' - ' . Filter::forFilesystem($this->Entity->entityData['title']);
        } elseif ($this->Entity instanceof Items) {
            return $this->Entity->entityData['category'] . ' - ' . Filter::forFilesystem($this->Entity->entityData['title']);
        }

        throw new ImproperActionException(sprintf('Entity of type %s is not allowed in this context', $this->Entity::class));
    }

    /**
     * Add attached files
     *
     * @param array<array-key, array<string, string>> $filesArr the files array
     */
    private function addAttachedFiles($filesArr): void
    {
        $real_names_so_far = array();
        $i = 0;
        $Config = Config::getConfig();
        $storage = (int) $Config->configArr['uploads_storage'];
        $storageFs = (new StorageFactory($storage))->getStorage()->getFs();
        foreach ($filesArr as $file) {
            $i++;
            $realName = $file['real_name'];
            // if we have a file with the same name, it shouldn't overwrite the previous one
            if (in_array($realName, $real_names_so_far, true)) {
                $realName = (string) $i . '_' . $realName;
            }
            $real_names_so_far[] = $realName;

            // add files to archive
            $this->Zip->addFileFromStream($this->folder . '/' . $realName, $storageFs->readStream($file['long_name']));
        }
    }

    /**
     * Add a PDF file to the ZIP archive
     */
    private function addPdf(): void
    {
        $userData = $this->Entity->Users->userData;
        $MpdfProvider = new MpdfProvider(
            $userData['fullname'],
            $userData['pdf_format'],
            (bool) $userData['pdfa'],
        );
        $MakePdf = new MakePdf($MpdfProvider, $this->Entity);
        $this->Zip->addFile($this->folder . '/' . $MakePdf->getFileName(), $MakePdf->getFileContent());
    }

    /**
     * This is where the magic happens
     *
     * @param int $id The id of the item we are zipping
     */
    private function addToZip(int $id, string $fullname): void
    {
        // we're making a backup so ignore permissions access
        $this->Entity->bypassReadPermission = true;
        $this->Entity->setId($id);
        $this->Entity->populate();
        $uploadedFilesArr = $this->Entity->Uploads->readAllNormal();
        $this->folder = Filter::forFilesystem($fullname) . '/' . $this->getBaseFileName();

        if (!empty($uploadedFilesArr)) {
            $this->addAttachedFiles($uploadedFilesArr);
        }
        $this->addPdf();
    }
}
