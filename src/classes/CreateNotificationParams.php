<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2021 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use Elabftw\Interfaces\CreateNotificationParamsInterface;

final class CreateNotificationParams extends ContentParams implements CreateNotificationParamsInterface
{
    public function __construct(private int $category, private array $body)
    {
        parent::__construct();
    }

    public function getCategory(): int
    {
        return $this->category;
    }

    public function getContent(): string
    {
        return json_encode($this->body, JSON_THROW_ON_ERROR, 512);
    }
}
