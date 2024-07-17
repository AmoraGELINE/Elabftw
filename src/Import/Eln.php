<?php

/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

declare(strict_types=1);

namespace Elabftw\Import;

use DateTimeImmutable;
use Elabftw\Elabftw\CreateUpload;
use Elabftw\Enums\Action;
use Elabftw\Enums\EntityType;
use Elabftw\Enums\FileFromString;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Exceptions\ResourceNotFoundException;
use Elabftw\Models\AbstractConcreteEntity;
use Elabftw\Models\AbstractTemplateEntity;
use Elabftw\Models\ExistingUser;
use Elabftw\Models\Experiments;
use Elabftw\Models\Uploads;
use Elabftw\Models\Users;
use JsonException;
use League\Flysystem\UnableToReadFile;

use function basename;
use function hash_file;
use function json_decode;
use function sprintf;

/**
 * Import a .eln file.
 */
class Eln extends AbstractZip
{
    public bool $importAuthorsAsUsers = true;

    // path where the metadata.json file lives (first folder found in archive)
    private string $root;

    // complete graph: all nodes from metadata json
    private array $graph;

    private array $linksToCreate = array();

    private array $insertedEntities = array();

    private array $crateNodeHasPart = array();

    public function processOnly(): array
    {
        $this->preProcess();
        return array(
            'parts' => count($this->crateNodeHasPart),
        );
    }

    public function import(): void
    {
        $this->preProcess();

        // loop over each hasPart of the root node
        // this is the main import loop
        foreach ($this->crateNodeHasPart as $part) {
            $this->importRootDataset($this->getNodeFromId($part['@id']));
        }

        // NOW CREATE THE LINKS
        // TODO avoid having 2 foreach loops...
        $result = array();
        foreach ($this->linksToCreate as $link) {
            foreach ($this->insertedEntities as $entity) {
                if ($link['link_@id'] === $entity['item_@id']) {
                    $result[] = array('origin_entity_type' => $link['origin_entity_type'], 'origin_id' => $link['origin_id'], 'link_id' => $entity['id'], 'link_entity_type' => $entity['entity_type']);
                    break;
                }
            }
        }

        foreach ($result as $linkToCreate) {
            $entity = $linkToCreate['origin_entity_type']->toInstance($this->Entity->Users, $linkToCreate['origin_id'], true);
            if ($linkToCreate['link_entity_type'] === EntityType::Experiments) {
                $entity->ExperimentsLinks->setId($linkToCreate['link_id']);
                $entity->ExperimentsLinks->postAction(Action::Create, array());
            } else {
                $entity->ItemsLinks->setId($linkToCreate['link_id']);
                $entity->ItemsLinks->postAction(Action::Create, array());
            }
        }
    }

    private function preProcess(): void
    {
        $this->root = $this->getRootDirectory();
        $this->graph = $this->getGraph();

        foreach ($this->graph as $node) {
            // find the node describing the crate
            if ($node['@id'] === './') {
                $this->crateNodeHasPart = $node['hasPart'];
            }
            // detect old elabftw (<5.0.0-beta2) versions where we need to decode characters
            // only newer versions have the areaServed attribute
            if ($node['@id'] === 'ro-crate-metadata.json' &&
                array_key_exists('sdPublisher', $node) &&
                $node['sdPublisher']['name'] === 'eLabFTW' &&
                !array_key_exists('areaServed', $node['sdPublisher'])) {
                $this->switchToEscapeOutput = true;
            }
        }
    }

    // figure out the path to the root of the eln (where the metadata file lives)
    // folder name is variable, so list folders and pick the first one found (there should be only one)
    private function getRootDirectory(): string
    {
        $listing = $this->fs->listContents($this->tmpDir);
        foreach ($listing as $item) {
            if ($item instanceof \League\Flysystem\DirectoryAttributes) {
                return $item->path();
            }
        }
        throw new ImproperActionException('Could not find a directory in the ELN archive!');
    }

    private function getGraph(): array
    {
        $metadataFile = 'ro-crate-metadata.json';
        try {
            $content = $this->fs->read($this->root . '/' . $metadataFile);
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (UnableToReadFile) {
            throw new ImproperActionException(sprintf(_('Error: could not read archive file properly! (missing %s)'), $metadataFile));
        } catch (JsonException $e) {
            throw new ImproperActionException($e->getMessage());
        }
        return $json['@graph'];
    }

    private function getNodeFromId(string $id): array
    {
        foreach ($this->graph as $node) {
            if ($node['@id'] === $id) {
                return $node;
            }
        }
        return array();
    }

    /**
     * This is one of the main Dataset `@type` node.
     */
    private function importRootDataset(array $dataset): void
    {
        $Author = $this->requester;
        if ($this->importAuthorsAsUsers) {
            // look for the Author node, and create the user if they do not exist
            $author = $this->getNodeFromId($dataset['author']['@id']);
            try {
                $Author = ExistingUser::fromEmail($author['email'] ?? 'nope');
                $Author->team = $this->requester->team;
            } catch (ResourceNotFoundException) {
                $Users = new Users(requester: $this->requester);
                try {
                    $Author = $Users->createFromPerson($author, $this->requester->team ?? 0);
                } catch (ImproperActionException) {
                    // Author will be the requester
                }
            }
        }

        // try and figure out if we are importing an experiment or a resource by looking at the genre
        $entityType = $this->entityType;
        // genre will override the entityType, unless we force it
        if (!empty($dataset['genre'])) {
            $entityType = $dataset['genre'] === 'experiment' ? EntityType::Experiments : EntityType::Items;
        }
        if ($this->forceEntityType) {
            $entityType = $this->entityType;
        }
        $this->Entity = $entityType->toInstance($Author);

        $this->Entity->Users = $Author;
        $this->Entity->bypassWritePermission = true;
        $title = $this->transformIfNecessary($dataset['name'] ?? _('Untitled'));

        // CATEGORY
        $categoryId = $this->defaultCategory;
        if (isset($dataset['about'])) {
            $categoryId = $this->getCategoryId($this->getNodeFromId($dataset['about']['@id'])['name']);
        }
        // items use the category id for create target
        $createTarget = $categoryId;

        if ($this->Entity instanceof AbstractConcreteEntity) {
            if ($this->Entity instanceof Experiments) {
                // no template
                $createTarget = -1;
            }
            $this->Entity->setId($this->Entity->create($createTarget, array()));
            // set the date if we can
            $date = date('Y-m-d');
            if (isset($dataset['dateCreated'])) {
                $dateCreated = new DateTimeImmutable($dataset['dateCreated']);
                $date = $dateCreated->format('Y-m-d');
            }
            $this->Entity->patch(Action::Update, array('date' => $date));
        } elseif ($this->Entity instanceof AbstractTemplateEntity) {
            $this->Entity->setId($this->Entity->create($title));
        }
        // keep a reference between the `@id` and the fresh id to resolve links later
        $this->insertedEntities[] = array('item_@id' => $dataset['@id'], 'id' => $this->Entity->id, 'entity_type' => $this->Entity->entityType);
        // here we use "text" or "description" attribute as main text
        $this->Entity->patch(Action::Update, array('title' => $title, 'bodyappend' => ($dataset['text'] ?? '') . ($dataset['description'] ?? '')));

        // now we import all the remaining attributes as text/links in the main text
        // we still have an allowlist of attributes imported, which also allows to switch between the kind of values expected
        $bodyAppend = '';
        foreach ($dataset as $attributeName => $value) {
            switch($attributeName) {
                case 'author':
                    $bodyAppend .= $this->authorToHtml($value);
                    break;
                    // CATEGORY
                case 'about':
                    $this->Entity->patch(Action::Update, array('category' => (string) $categoryId));
                    break;
                    // COMMENTS
                case 'comment':
                    foreach ($value as $comment) {
                        // for backward compatibility with elabftw's .eln from before 4.9, the "comment" attribute MAY contain all, instead of just being a link with an @id
                        // after 4.9 the "comment" attribute contains only a link to an @type: Comment node
                        if (count($comment) === 1) {
                            // resolve the id to get the full node content
                            $comment = $this->getNodeFromId($comment['@id']);
                        }
                        $author = $this->getNodeFromId($comment['author']['@id']);
                        $content = sprintf(
                            "Imported comment from %s %s (%s)\n\n%s",
                            $this->transformIfNecessary($author['givenName'] ?? ''),
                            $this->transformIfNecessary($author['familyName'] ?? '') ?: $author['name'] ?? 'Unknown',
                            $comment['dateCreated'],
                            $this->transformIfNecessary($comment['text'] ?? '', true),
                        );
                        $this->Entity->Comments->postAction(Action::Create, array('comment' => $content));
                    }
                    break;

                case 'citation':
                case 'license':
                    $bodyAppend .= sprintf('<h1>%s</h1><ul><li><a href="%s">%s</a></li></ul>', _(ucfirst($attributeName)), $value['@id'], $value['@id']);
                    break;
                case 'funder':
                    $bodyAppend .= $this->attrToHtml($value, _(ucfirst($attributeName)));
                    break;
                    // LINKS
                case 'mentions':
                    foreach($value as $mention) {
                        // for backward compatibility with elabftw's .eln from before 4.9, the "mention" attribute MAY contain all, instead of just being a link with an @id
                        // after 4.9 the "mention" attribute contains only a link to an @type: Dataset node
                        // after 5.1 the "mention" will point to a Dataset contained in the .eln
                        if (count($mention) === 1) {
                            // store a reference for the link to create. We cannot create it now as link might or might not exist yet.
                            $this->linksToCreate[] = array('origin_entity_type' => $this->Entity->entityType, 'origin_id' => $this->Entity->id, 'link_@id' => $mention['@id']);
                        }
                    }
                    break;

                    // METADATA
                case 'variableMeasured':
                    foreach ($value ?? array() as $propval) {
                        // we look for the special elabftw_metadata property and that's what we import
                        if ($propval['propertyID'] === 'elabftw_metadata') {
                            $this->Entity->patch(Action::Update, array('metadata' => $propval['value']));
                        }
                        break;
                    }
                    break;

                    // RATING
                case 'aggregateRating':
                    $this->Entity->patch(Action::Update, array('rating' => $value['ratingValue'] ?? '0'));
                    break;
                    // STATUS
                case 'creativeWorkStatus':
                    $this->Entity->patch(Action::Update, array('status' => (string) $this->getStatusId($value)));
                    break;
                    // STEPS
                case 'step':
                    foreach ($value as $step) {
                        $this->Entity->Steps->importFromHowToStep($step);
                    }
                    break;
                    // TAGS: should normally be a comma separated string, but we allow array for BC
                case 'keywords':
                    $tags = $value;
                    if (is_string($tags)) {
                        $tags = explode(',', $tags);
                    }
                    foreach ($tags as $tag) {
                        if (!empty($tag)) {
                            $this->Entity->Tags->postAction(
                                Action::Create,
                                array('tag' => $this->transformIfNecessary($tag)),
                            );
                        }
                    }
                    break;

                default:
            }
        }
        $this->Entity->patch(Action::Update, array('bodyappend' => $bodyAppend));

        // also save the Dataset node as a .json file so we don't lose information with things not imported
        $this->Entity->Uploads->postAction(Action::CreateFromString, array(
            'file_type' => FileFromString::Json->value,
            'real_name' => 'dataset-node-from-ro-crate.json',
            'content' => json_encode($dataset, JSON_THROW_ON_ERROR, 1024),
        ));

        $this->inserted++;
        // now loop over the parts of this node to find the rest of the files
        // the getNodeFromId might return nothing but that's okay, we just continue to try and find stuff
        foreach ($dataset['hasPart'] as $part) {
            $this->importPart($this->getNodeFromId($part['@id']));
        }
    }

    private function authorToHtml(array $node): string
    {
        $html = sprintf('<h1>%s</h1><ul>', _('Author'));
        $fullNode = $this->getNodeFromId($node['@id']);
        $html .= sprintf(
            '<li>%s %s %s</li>',
            $this->transformIfNecessary($fullNode['givenName'] ?? ''),
            $this->transformIfNecessary($fullNode['familyName'] ?? ''),
            $this->transformIfNecessary($fullNode['identifier'] ?? ''),
        );
        return $html . '</ul>';
    }

    private function attrToHtml(array $attr, string $title): string
    {
        $html = sprintf('<h1>%s</h1><ul>', $title);
        foreach ($attr as $elem) {
            if (is_string($elem)) {
                $html .= sprintf('<li><a href="%s">%s</a></li>', $elem, $elem);
                continue;
            }
            $node = $this->getNodeFromId($elem['@id']);
            $html .= sprintf('<li><a href="%s">%s</a></li>', $node['@id'], $node['name']);
        }
        return $html . '</ul>';
    }

    private function importPart(array $part): void
    {
        if (empty($part['@type'])) {
            return;
        }

        switch ($part['@type']) {
            case 'Dataset':
                $this->Entity->patch(Action::Update, array('bodyappend' => $this->part2html($part)));
                foreach ($part['hasPart'] as $subpart) {
                    if ($subpart['@type'] === 'File') {
                        $this->importFile($subpart);
                    }
                }
                break;
            case 'File':
                if (str_starts_with($part['@id'], 'http')) {
                    // we don't import remote files
                    return;
                }
                $this->importFile($part);
                break;
            default:
                return;
        }
    }

    private function importFile(array $file): void
    {
        // note: path transversal vuln is detected and handled by flysystem
        $filepath = $this->tmpPath . '/' . basename($this->root) . '/' . $file['@id'];
        // checksum is mandatory for import
        if (empty($file['sha256']) || hash_file('sha256', $filepath) !== $file['sha256']) {
            throw new ImproperActionException(sprintf('Error during import: %s has incorrect sha256 sum.', basename($filepath)));
        }
        $newUploadId = $this->Entity->Uploads->create(new CreateUpload(
            $file['name'] ?? basename($file['@id']),
            $filepath,
            $this->transformIfNecessary($file['description'] ?? '', true) ?: null,
        ));
        // the alternateName holds the previous long_name of the file
        if (!empty($file['alternateName'])) {
            // read the newly created upload so we can get the new long_name to replace the old in the body
            $Uploads = new Uploads($this->Entity, $newUploadId);
            $currentBody = $this->Entity->readOne()['body'];
            $newBody = str_replace($file['alternateName'], $Uploads->uploadData['long_name'], $currentBody);
            $this->Entity->patch(Action::Update, array('body' => $newBody));
        }
    }

    private function part2html(array $part): string
    {
        $html = sprintf('<p>%s<br>%s', $part['name'] ?? '', $part['dateCreated'] ?? '');
        $html .= '<ul>';
        foreach ($part['hasPart'] as $subpart) {
            $html .= sprintf(
                '<li>%s %s</li>',
                basename($subpart['@id']),
                $this->transformIfNecessary($subpart['description'] ?? ''),
            );
        }
        $html .= '</ul>';
        return $html;
    }
}
