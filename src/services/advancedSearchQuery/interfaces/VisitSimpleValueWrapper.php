<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @author Marcel Bolten <github@marcelbolten.de>
 * @copyright 2021 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services\AdvancedSearchQuery\Interfaces;

use Elabftw\Services\AdvancedSearchQuery\Collectors\InvalidFieldCollector;
use Elabftw\Services\AdvancedSearchQuery\Collectors\WhereCollector;
use Elabftw\Services\AdvancedSearchQuery\Grammar\SimpleValueWrapper;
use Elabftw\Services\AdvancedSearchQuery\Visitors\VisitorParameters;

interface VisitSimpleValueWrapper
{
    public function visitSimpleValueWrapper(SimpleValueWrapper $simpleValueWrapper, VisitorParameters $parameters): InvalidFieldCollector | WhereCollector | int;
}
