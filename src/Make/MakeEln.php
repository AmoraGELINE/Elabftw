<?php

/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

declare(strict_types=1);

namespace Elabftw\Make;

use DateTimeImmutable;
use Elabftw\Elabftw\Tools;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ResourceNotFoundException;
use Elabftw\Models\AbstractEntity;
use Elabftw\Models\Config;
use Elabftw\Models\Experiments;
use Elabftw\Models\Items;
use Elabftw\Models\Users;
use Elabftw\Services\Filter;
use League\Flysystem\UnableToReadFile;
use ZipStream\ZipStream;

/**
 * Make an ELN archive
 */
class MakeEln extends AbstractMakeEln
{
    public function __construct(protected ZipStream $Zip, protected Users $requester, protected array $entitySlugs)
    {
        parent::__construct($Zip);
    }

    /**
     * Loop on each id and add it to our eln archive
     */
    public function getStreamZip(): void
    {
        foreach ($this->entitySlugs as $slug) {
            try {
                $entity = $slug->type->toInstance($this->requester, $slug->id);
            } catch (IllegalActionException | ResourceNotFoundException) {
                continue;
            }
            $this->processEntity($entity);
        }
        // add the description of root with hasPart property
        $this->dataEntities[] = array(
            '@id' => './',
            '@type' => 'Dataset',
            'hasPart' => $this->rootParts,
        );

        // merge all, including authors
        $this->dataArr['@graph'] = array_merge($this->dataArr['@graph'], $this->getCreateActionNode(), $this->dataEntities, $this->authors);

        // add the metadata json file containing references to all the content of our crate
        $this->Zip->addFile($this->root . '/ro-crate-metadata.json', json_encode($this->dataArr, JSON_THROW_ON_ERROR, 512));
        $this->Zip->finish();
    }

    private static function toSlug(AbstractEntity $entity): string
    {
        return sprintf('%s:%d', $entity->entityType->value, $entity->id ?? 0);
    }

    private static function getDatasetFolderName(array $entityData): string
    {
        $prefix = '';
        if (!empty($entityData['category_title'])) {
            $prefix = Filter::forFilesystem($entityData['category_title']) . ' - ';
        }
        // prevent a zip name with too many characters, see #3966
        $prefixedTitle = substr($prefix . Filter::forFilesystem($entityData['title']), 0, 100);
        return sprintf('%s - %s', $prefixedTitle, Tools::getShortElabid($entityData['elabid'] ?? ''));
    }

    private function processEntity(AbstractEntity $entity): bool
    {
        // experiments:123 or items:123
        $slug = self::toSlug($entity);
        // only process an entity once
        if (in_array($slug, $this->processedEntities, true)) {
            return false;
        }
        $e = $entity->entityData;
        $hasPart = array();
        $currentDatasetFolder = self::getDatasetFolderName($e);
        $this->processedEntities[] = $slug;
        $this->folder = $this->root . '/' . $currentDatasetFolder;
        $this->rootParts[] = array('@id' => './' . $currentDatasetFolder);
        // COMMENTS
        $comments = array();
        foreach ($e['comments'] as $comment) {
            // the comment creation date will be used as part of the id
            $dateCreated = (new DateTimeImmutable($comment['created_at']))->format(DateTimeImmutable::ATOM);
            $id = 'comment://' . urlencode($dateCreated);
            // we add the reference to the comment in hasPart
            $comments[] = array('@id' => $id);
            // now we build a root node for the comment, with the same id as the one referenced in the main entity
            $firstname = $comment['firstname'] ?? '';
            $lastname = $comment['lastname'] ?? '';
            $this->dataEntities[] = array(
                '@id' => $id,
                '@type' => 'Comment',
                'dateCreated' => $dateCreated,
                'text' => $comment['comment'],
                'author' => array('@id' => $this->getAuthorId($comment['userid'], $firstname, $lastname, $comment['orcid'])),
            );
        }
        // TAGS
        $keywords = array();
        if ($e['tags']) {
            // the keywords value is a comma separated list
            // let's hope no one has a comma in their tags...
            $keywords = implode(',', explode('|', (string) $e['tags']));
        }

        // UPLOADS
        $uploadedFilesArr = $e['uploads'];
        if (!empty($uploadedFilesArr)) {
            try {
                // this gets modified by the function so we have the correct real_names
                $uploadedFilesArr = $this->addAttachedFiles($uploadedFilesArr);
            } catch (UnableToReadFile) {
            }
            foreach ($uploadedFilesArr as $file) {
                $uploadAtId = './' . $currentDatasetFolder . '/' . $file['real_name'];
                $hasPart[] = array('@id' => $uploadAtId);
                $fileNode = array(
                    '@id' => $uploadAtId,
                    '@type' => 'File',
                    'name' => $file['real_name'],
                    'alternateName' => $file['long_name'],
                    'contentSize' => $file['filesize'],
                    'sha256' => $file['hash'] ?? hash_file('sha256', $uploadAtId),
                );
                // add the file comment as description but only if it's present
                if (!empty($file['comment'])) {
                    $fileNode['description'] = $file['comment'];
                }
                $this->dataEntities[] = $fileNode;
            }
        }
        // LINKS (mentions)
        // this array will be added to the "mentions" attribute of the main dataset
        $mentions = array();
        $linkTypes = array('experiments', 'items');
        foreach($linkTypes as $type) {
            foreach ($e[$type . '_links'] as $link) {
                if ($type === 'items') {
                    $link = new Items($this->requester, $link['entityid']);
                } else {
                    $link = new Experiments($this->requester, $link['entityid']);
                }
                $mentions[] = array('@id' => './' . self::getDatasetFolderName($link->entityData));
                // WARNING: recursion!
                $this->processEntity($link);
            }
        }
        $firstname = $e['firstname'] ?? '';
        $lastname = $e['lastname'] ?? '';
        $datasetNode = array(
            '@id' => './' . $currentDatasetFolder,
            '@type' => 'Dataset',
            'author' => array('@id' => $this->getAuthorId($e['userid'], $firstname, $lastname, $e['orcid'])),
            'alternateName' => $e['custom_id'] ?? '',
            'dateCreated' => (new DateTimeImmutable($e['created_at']))->format(DateTimeImmutable::ATOM),
            'dateModified' => (new DateTimeImmutable($e['modified_at']))->format(DateTimeImmutable::ATOM),
            'identifier' => $e['elabid'] ?? '',
            'comment' => $comments,
            'keywords' => $keywords,
            'name' => $e['title'],
            'text' => $e['body'] ?? '',
            'url' => Config::fromEnv('SITE_URL') . '/' . $entity->page . '.php?mode=view&id=' . $e['id'],
            'hasPart' => $hasPart,
            'mentions' => $mentions,
            'additionalType' => $entity->entityType->value,
        );
        if ($e['category_title'] !== null) {
            $datasetNode['category'] = $e['category_title'];
        }
        if ($e['status_title'] !== null) {
            $datasetNode['status'] = $e['status_title'];
        }
        $this->dataEntities[] = $datasetNode;
        return true;
    }

    /**
     * Generate an author node unless it exists already
     */
    private function getAuthorId(int $userid, string $firstname, string $lastname, ?string $orcid): string
    {
        // add firstname and lastname to the hash to get more entropy. Use the userid too so similar names won't collide.
        $id = sprintf('person://%s?hash_algo=%s', hash(self::HASH_ALGO, (string) $userid . $firstname . $lastname), self::HASH_ALGO);
        $node = array(
            '@id' => $id,
            '@type' => 'Person',
            'familyName' => $lastname,
            'givenName' => $firstname,
        );
        // only add an identifier property if there is an orcid
        if ($orcid !== null) {
            $node['identifier'] = 'https://orcid.org/' . $orcid;
        }
        // only add it if it doesn't exist yet in our list of authors
        if (!in_array($id, array_column($this->authors, '@id'), true)) {
            $this->authors[] = $node;
        }
        return $id;
    }
}
