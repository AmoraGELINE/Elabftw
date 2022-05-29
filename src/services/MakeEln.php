<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use function basename;
use DateTimeImmutable;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Models\AbstractEntity;
use const SITE_URL;
use ZipStream\ZipStream;

/**
 * Make an ELN archive
 */
class MakeEln extends MakeStreamZip
{
    protected string $extension = '.eln.zip';

    private DateTimeImmutable $now;

    // name of the folder containing everything
    private string $root;

    public function __construct(protected ZipStream $Zip, AbstractEntity $entity, protected array $idArr)
    {
        parent::__construct($Zip, $entity, $idArr);
        $this->now = new DateTimeImmutable();
        $this->root = $this->now->format('Y-m-d') . '-ro-crate';
        $this->jsonArr = array(
            '@context' => 'https://w3id.org/ro/crate/1.1/context',
            '@graph' => array(
                array(
                    '@id' => 'ro-crate-metadata.json',
                    '@type' => 'CreativeWork',
                    'about' => array('@id' => './'),
                    'conformsTo' => array('@id' => 'https://w3id.org/ro/crate/1.1'),
                    'dateCreated' => $this->now->format(DateTimeImmutable::ISO8601),
                    'sdPublisher' => array(
                        '@type' => 'Organization',
                        'name' => 'eLabFTW',
                        'logo' => 'https://www.elabftw.net/img/elabftw-logo-only.svg',
                        'slogan' => 'A free and open source electronic lab notebook.',
                        'url' => 'https://www.elabftw.net',
                        'parentOrganization' => array(
                          '@type' => 'Organization',
                          'name' => 'Deltablot',
                          'logo' => 'https://www.deltablot.com/img/logos/deltablot.svg',
                          'slogan' => 'Open Source software for research labs.',
                          'url' => 'https://www.deltablot.com',
                        ),
                    ),
                    'version' => '1.0',
                ),
            ),
        );
    }

    public function getFileName(): string
    {
        return $this->root . $this->extension;
    }

    /**
     * Loop on each id and add it to our eln archive
     */
    public function getStreamZip(): void
    {
        $dataEntities = array();
        foreach ($this->idArr as $id) {
            $this->Entity->setId((int) $id);
            try {
                $permissions = $this->Entity->getPermissions();
            } catch (IllegalActionException $e) {
                return;
            }
            $e = $this->Entity->entityData;
            if ($permissions['read']) {
                $this->folder = $this->root . '/' . $this->getBaseFileName();
                $dataEntities[] =  array(
                    '@id' => './' . basename($this->folder),
                    '@type' => 'Dataset',
                    'author' => array(
                        '@type' => 'Person',
                        'email' => $e['email'],
                        'familyName' => $e['lastname'],
                        'givenName' => $e['firstname'],
                    ),
                    'dateCreated' => $e['datetime'],
                    'dateModified' => $e['lastchange'],
                    'name' => $e['title'],
                    'text' => $e['body'],
                    'url' => SITE_URL . '/' . $this->Entity->page . '.php?mode=view&id=' . $e['id'],
                );

                // CSV
                $MakeCsv = $this->getCsv((int) $id);
                $csv = $MakeCsv->getFileContent();
                $this->Zip->addFile($this->folder . '/' . $MakeCsv->getFileName(), $csv);
                $dataEntities[] = array(
                    '@id' => './' . $MakeCsv->getFileName(),
                    '@type' => 'File',
                    'description' => 'CSV Export',
                    'name' => $MakeCsv->getFileName(),
                    'contentType' => $MakeCsv->getContentType(),
                    'contentSize' => (string) $MakeCsv->getContentSize(),
                    'sha256' => hash('sha256', $csv),
                );

                // PDF
                $MakePdf = $this->getPdf();
                $pdf = $MakePdf->getFileContent();
                $this->Zip->addFile($this->folder . '/' . $MakePdf->getFileName(), $pdf);
                $dataEntities[] = array(
                    '@id' => './' . basename($this->folder) . '.pdf',
                    '@type' => 'File',
                    'description' => 'PDF Export',
                    'name' => $MakePdf->getFileName(),
                    'contentType' => $MakePdf->getContentType(),
                    'contentSize' => (string) $MakePdf->getContentSize(),
                    'sha256' => hash('sha256', $pdf),
                );
            }
        }
        $this->jsonArr['@graph'] = array_merge($this->jsonArr['@graph'], $dataEntities);

        // add the metadata json file containing references to all the content of our crate
        $this->Zip->addFile($this->root . '/ro-crate-metadata.json', json_encode($this->jsonArr, JSON_THROW_ON_ERROR, 512));
        $this->Zip->finish();
    }
}
