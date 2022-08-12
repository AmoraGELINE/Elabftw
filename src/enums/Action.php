<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Enums;

enum Action: string
{
    case Archive = 'archive';
    case Bloxberg = 'bloxberg';
    case Create = 'create';
    case Duplicate = 'duplicate';
    case Lock = 'lock';
    case Finish = 'finish';
    case Notif = 'notif';
    case Pin = 'pin';
    case Timestamp = 'timestamp';
    case Validate = 'validate';
}
