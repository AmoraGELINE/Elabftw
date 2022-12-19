<?php
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use DateTime;
use Elabftw\Enums\Orderby;
use Elabftw\Enums\Sort;
use Elabftw\Services\Check;
use function memory_get_usage;
use function microtime;
use function round;
use Symfony\Component\HttpFoundation\Request;

/**
 * Functions used by Twig in templates
 */
class TwigFunctions
{
    /**
     * Get an array of integer with valid number of items per page based on the current limit
     *
     * @param int $input the current limit for the page
     */
    public static function getLimitOptions(int $input): array
    {
        $limits = array(10, 20, 50, 100);
        // if the current limit is already a standard one, no need to include it
        if (in_array($input, $limits, true)) {
            return $limits;
        }
        // now find the place where to include our limit
        $place = count($limits);
        foreach ($limits as $key => $limit) {
            if ($input < $limit) {
                $place = $key;
                break;
            }
        }
        array_splice($limits, $place, 0, array($input));
        return $limits;
    }

    public static function getGenerationTime(): float
    {
        $Request = Request::createFromGlobals();
        return round(microtime(true) - $Request->server->get('REQUEST_TIME_FLOAT'), 5);
    }

    public static function getMemoryUsage(): int
    {
        return memory_get_usage();
    }

    public static function getNumberOfQueries(): int
    {
        $Db = Db::getConnection();
        return $Db->getNumberOfQueries();
    }

    public static function getMinPasswordLength(): int
    {
        return Check::MIN_PASSWORD_LENGTH;
    }

    /**
     * Get a formatted date relative to now
     * Input is fed directly to the modify function of DateTime
     * Output format works for SQL Datetime column (like step deadline)
     */
    public static function toDatetime(string $input): string
    {
        return (new DateTime())->modify($input)->format('Y-m-d H:i:s');
    }

    public static function extractJson(string $json, string $key): string|bool|int
    {
        $decoded = json_decode($json, true, 3, JSON_THROW_ON_ERROR);
        if ($decoded[$key]) {
            return (int) $decoded[$key];
        }
        return false;
    }

    public static function isInJsonArray(string $json, string $key, int $target): bool
    {
        $decoded = json_decode($json, true, 3, JSON_THROW_ON_ERROR);
        if (in_array($target, $decoded[$key], true)) {
            return true;
        }
        return false;
    }

    public static function getSortIcon(string $orderBy): string
    {
        $Request = Request::createFromGlobals();
        if (Orderby::tryFrom($orderBy) === Orderby::tryFrom($Request->query->getAlpha('order'))) {
            switch (Sort::tryFrom($Request->query->getAlpha('sort'))) {
                case Sort::Asc:
                    return 'fa-sort-up';
                case Sort::Desc:
                    return 'fa-sort-down';
            }
        }
        return 'fa-sort';
    }
}
