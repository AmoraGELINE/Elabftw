<?php

/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

declare(strict_types=1);

namespace Elabftw\Make;

use Elabftw\Enums\EntityType;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Models\Users;

use function date;

/**
 * Make a CSV file from a list of id and a type
 */
class MakeCsv extends AbstractMakeCsv
{
    public function __construct(private Users $requester, private EntityType $entityType, private array $idArr)
    {
        parent::__construct();
    }

    /**
     * Return a nice name for the file
     */
    public function getFileName(): string
    {
        return date('Y-m-d_H-i-s') . '-export.elabftw.csv';
    }

    /**
     * Here we populate the first row: it will be the column names
     */
    protected function getHeader(): array
    {
        return array('id', 'date', 'title', 'body', 'category', 'category_title', 'category_color', 'status', 'status_title', 'status_color', 'custom_id', 'elabid', 'rating', 'url', 'metadata', 'tags');
    }

    /**
     * Generate an array for the requested data
     */
    protected function getRows(): array
    {
        $entity = $this->entityType->toInstance($this->requester);
        $rows = array();
        foreach ($this->idArr as $id) {
            try {
                $entity->setId((int) $id);
                $permissions = $entity->getPermissions();
            } catch (IllegalActionException) {
                continue;
            }
            if ($permissions['read']) {
                $row = array(
                    $entity->entityData['id'],
                    $entity->entityData['date'],
                    htmlspecialchars_decode((string) $entity->entityData['title'], ENT_QUOTES | ENT_COMPAT),
                    html_entity_decode(strip_tags(htmlspecialchars_decode((string) $entity->entityData['body'], ENT_QUOTES | ENT_COMPAT))),
                    (string) $entity->entityData['category'],
                    htmlspecialchars_decode((string) $entity->entityData['category_title'], ENT_QUOTES | ENT_COMPAT),
                    (string) $entity->entityData['category_color'],
                    (string) $entity->entityData['status'],
                    htmlspecialchars_decode((string) $entity->entityData['status_title'], ENT_QUOTES | ENT_COMPAT),
                    (string) $entity->entityData['status_color'],
                    $entity->entityData['custom_id'] ?? '',
                    $entity->entityData['elabid'] ?? '',
                    $entity->entityData['rating'],
                    $entity->entityData['sharelink'],
                    $entity->entityData['metadata'] ?? '',
                    $entity->entityData['tags'] ?? '',
                );
                $rows[] = $row;
            }
        }
        return $rows;
    }
}
