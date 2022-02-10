<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use function count;
use Elabftw\Elabftw\ContentParams;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\AbstractEntity;
use Elabftw\Models\Config;
use Elabftw\Models\Experiments;
use Elabftw\Models\Items;
use function json_encode;
use PDO;
use ZipStream\ZipStream;

/**
 * Make a zip archive from experiment or db item
 */
class MakeStreamZip extends AbstractMake
{
    private ZipStream $Zip;

    // files to be deleted by destructor
    private array $trash = array();

    private string $folder = '';

    // array that will be converted to json
    private array $jsonArr = array();

    public function __construct(AbstractEntity $entity, private array $idArr)
    {
        parent::__construct($entity);

        // we check first if the zip extension is here
        if (!class_exists('ZipArchive')) {
            throw new ImproperActionException('Fatal error! Missing extension: php-zip. Make sure it is installed and activated.');
        }

        $this->Zip = new ZipStream();
    }

    /**
     * Clean up the temporary files (csv and pdf)
     */
    public function __destruct()
    {
        foreach ($this->trash as $file) {
            unlink($file);
        }
    }

    /**
     * Get the name of the generated file
     */
    public function getFileName(): string
    {
        if (count($this->idArr) === 1) {
            $this->Entity->setId((int) $this->idArr[0]);
            $this->Entity->canOrExplode('read');
            return $this->getBaseFileName() . '.zip';
        }
        return 'export.elabftw.zip';
    }

    /**
     * Loop on each id and add it to our zip archive
     * This could be called the main function.
     */
    public function getZip(): void
    {
        foreach ($this->idArr as $id) {
            $this->addToZip((int) $id);
        }

        // add the (hidden) .elabftw.json file useful for reimport
        $this->Zip->addFile('.elabftw.json', json_encode($this->jsonArr, JSON_THROW_ON_ERROR, 512));

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
        $StorageManager = new StorageManager($storage);
        $storageFs = $StorageManager->getStorageFs();
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
     * Add the .asn1 token and the timestamped pdf to the zip archive
     *
     * TODO this is duplicated in MakeBackupZip, and this could simply be merged in addAttachedFiles
     * maybe when timestamped uploads are not treated specially this could be easier, with the readAll including these files already
     * @param int $id The id of current item we are zipping
     */
    private function addTimestampFiles(int $id): void
    {
        if ($this->Entity instanceof Experiments && $this->Entity->entityData['timestamped']) {
            $Config = Config::getConfig();
            $storage = (int) $Config->configArr['uploads_storage'];
            $StorageManager = new StorageManager($storage);
            $storageFs = $StorageManager->getStorageFs();

            // SQL to get the path of the token
            $sql = "SELECT real_name, long_name FROM uploads WHERE item_id = :id AND (
                type = 'timestamp-token'
                OR type = 'exp-pdf-timestamp') LIMIT 2";
            $req = $this->Db->prepare($sql);
            $req->bindParam(':id', $id, PDO::PARAM_INT);
            $req->execute();
            $uploads = $this->Db->fetchAll($req);
            foreach ($uploads as $upload) {
                // add it to the .zip
                $this->Zip->addFileFromStream(
                    $this->folder . '/' . $upload['real_name'],
                    $storageFs->readStream($upload['long_name']),
                );
            }
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
        $MakePdf = new MakePdf($MpdfProvider, $this->Entity, true);
        $MakePdf->outputToFile();
        $this->Zip->addFileFromPath($this->folder . '/' . $MakePdf->getFileName(), $MakePdf->filePath);
        $this->trash[] = $MakePdf->filePath;
    }

    /**
     * Add a CSV file to the ZIP archive
     *
     * @param int $id The id of the item we are zipping
     */
    private function addCsv(int $id): void
    {
        $MakeCsv = new MakeCsv($this->Entity, array($id));
        $this->Zip->addFile($this->folder . '/' . $this->folder . '.csv', $MakeCsv->getFileContent());
    }

    /**
     * This is where the magic happens
     *
     * @param int $id The id of the item we are zipping
     */
    private function addToZip(int $id): void
    {
        $this->Entity->setId($id);
        $permissions = $this->Entity->getPermissions();
        if ($permissions['read']) {
            $uploadedFilesArr = $this->Entity->Uploads->readAllNormal();
            $entityArr = $this->Entity->entityData;
            // save the uploads in entityArr for the json file
            $entityArr['uploads'] = $uploadedFilesArr;
            // add links
            $entityArr['links'] = $this->Entity->Links->read(new ContentParams());
            // add steps
            $entityArr['steps'] = $this->Entity->Steps->read(new ContentParams());
            $this->folder = $this->getBaseFileName();

            $this->addTimestampFiles($id);
            if (!empty($uploadedFilesArr)) {
                $this->addAttachedFiles($uploadedFilesArr);
            }
            $this->addCsv($id);
            $this->addPdf();
            // add an entry to the json file
            $this->jsonArr[] = $entityArr;
        }
    }
}
