<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use function dirname;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\FilesystemErrorException;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Exceptions\ResourceNotFoundException;
use Elabftw\Exceptions\UnauthorizedException;
use Elabftw\Factories\ProcessorFactory;
use Elabftw\Models\ApiKeys;
use Elabftw\Models\Config;
use Elabftw\Models\Experiments;
use Elabftw\Models\ItemsTypes;
use Elabftw\Models\Links;
use Elabftw\Models\Status;
use Elabftw\Models\Tags;
use Elabftw\Models\Users2Teams;
use Elabftw\Services\MakeBloxberg;
use Elabftw\Services\MakeCustomTimestamp;
use Elabftw\Services\MakeDfnTimestamp;
use Elabftw\Services\MakeDigicertTimestamp;
use Elabftw\Services\MakeGlobalSignTimestamp;
use Elabftw\Services\MakeSectigoTimestamp;
use Elabftw\Services\MakeUniversignTimestamp;
use Elabftw\Services\MakeUniversignTimestampDev;
use Elabftw\Services\TimestampUtils;
use Exception;
use GuzzleHttp\Client;
use PDOException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * This is the main endpoint for requests. It can deal with json requests or classical forms.
 */
require_once dirname(__DIR__) . '/init.inc.php';

// the default response is a failed json response
$Response = new JsonResponse();
$Response->setData(array(
    'res' => false,
    'msg' => Tools::error(),
));
// this is the result of the processed action
$res = '';

try {
    $Processor = (new ProcessorFactory)->getProcessor($App->Users, $Request);
    $action = $Processor->getAction();
    $Model = $Processor->getModel();
    $Params = $Processor->getParams();
    $target = $Processor->getTarget();


    // all non read actions for status and items types are limited to admins
    if ($action !== 'read' &&
        ($Model instanceof Status || $Model instanceof ItemsTypes) &&
        !$App->Session->get('is_admin')
    ) {
        throw new IllegalActionException('Non admin user tried to edit status or items types.');
    }
    // only sysadmins can update the config
    if (((($action === 'create' || $action === 'destroy') && $Model instanceof Users2Teams)) && !$App->Users->userData['is_sysadmin']) {
        throw new IllegalActionException('Non sysadmin user tried to update instance config or edit users2teams.');
    }

    if ($action === 'create') {
        $res = $Model->create($Params);
        if ($Model instanceof ApiKeys) {
            $res = $Params->getKey();
        }
    } elseif ($action === 'read') {
        $res = $Model->read($Params);
    } elseif ($action === 'update') {
        $res = $Model->update($Params);
    } elseif ($action === 'replace') {
        $res = $Model->replace($Params);
    } elseif ($action === 'destroy') {
        if ($Model instanceof Experiments) {
            $res = $Model->destroy();
        } elseif ($Model instanceof Users2Teams) {
            $res = $Model->destroy($Params);
        } else {
            $res = $Model->destroy();
        }
    } elseif ($action === 'deduplicate' && $Model instanceof Tags) {
        $res = $Model->deduplicate();
    } elseif ($action === 'importlinks' && $Model instanceof Links) {
        $res = $Model->import();
    // TIMESTAMP
    } elseif ($action === 'timestamp' && $Model instanceof Experiments) {
        $config = $App->Config->configArr;
        // CLASSIC
        if ($target === 'ts_classic') {
            if ($config['ts_authority'] === 'dfn') {
                $Maker = new MakeDfnTimestamp($config, $Model);
            } elseif ($config['ts_authority'] === 'universign') {
                if ($config['debug']) {
                    // this will use the sandbox endpoint
                    $Maker = new MakeUniversignTimestampDev($config, $Model);
                } else {
                    $Maker = new MakeUniversignTimestamp($config, $Model);
                }
            } elseif ($config['ts_authority'] === 'digicert') {
                $Maker = new MakeDigicertTimestamp($config, $Model);
            } elseif ($config['ts_authority'] === 'sectigo') {
                $Maker = new MakeSectigoTimestamp($config, $Model);
            } elseif ($config['ts_authority'] === 'globalsign') {
                $Maker = new MakeGlobalSignTimestamp($config, $Model);
            } else {
                $Maker = new MakeCustomTimestamp($config, $Model);
            }

            $pdfBlob = $Maker->generatePdf();
            $TimestampUtils = new TimestampUtils(
                new Client(),
                $pdfBlob,
                $Maker->getTimestampParameters(),
                new TimestampResponse(),
            );
            $tsResponse = $TimestampUtils->timestamp();
            $res = $Maker->saveTimestamp($TimestampUtils->getDataPath(), $tsResponse);
        // BLOXBERG
        } elseif ($target === 'ts_bloxberg') {
            if ($config['blox_enabled'] !== '1') {
                throw new ImproperActionException('Bloxberg timestamping is disabled on this instance.');
            }
            $Make = new MakeBloxberg(new Client(), $Model);
            $res = $Make->timestamp();
        }
    }

    // special case for uploading an edited json file back: it's a POSTed async form
    // for the rest of the cases, we redirect to the entity page edit mode because IIRC only the attached file update feature will use this
    if ($Processor instanceof FormProcessor && !($Request->request->get('extraParam') === 'jsoneditor' || $Request->request->get('extraParam') === 'noRedirect')) {
        $Response = new RedirectResponse('../../' . $Processor->Entity->page . '.php?mode=edit&id=' . $Processor->Entity->id);
        $Response->send();
        exit;
    }

    // the value param can hold a value used in the page
    $Response->setData(array(
        'res' => true,
        'msg' => _('Saved'),
        'value' => $res,
    ));
} catch (ImproperActionException | UnauthorizedException | ResourceNotFoundException | PDOException $e) {
    $Response->setData(array(
        'res' => false,
        'msg' => $e->getMessage(),
    ));
} catch (IllegalActionException $e) {
    $App->Log->notice('', array(array('userid' => $App->Session->get('userid')), array('IllegalAction', $e)));
    $Response->setData(array(
        'res' => false,
        'msg' => Tools::error(true),
    ));
} catch (DatabaseErrorException | FilesystemErrorException $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Error', $e)));
    $Response = new JsonResponse();
    $Response->setData(array(
        'res' => false,
        'msg' => $e->getMessage(),
    ));
} catch (Exception $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid') ?? 'anon'), array('Exception' => $e)));
    $Response->setData(array(
        'res' => false,
        'msg' => Tools::error(),
    ));
} finally {
    $Response->send();
}
