<?php
/**
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Swift_TransportException;

require_once \dirname(__DIR__) . '/init.inc.php';

// default location to redirect to
$location = '../../login.php';

try {
    // check for disabled local register
    if ($App->Config->configArr['local_register'] === '0') {
        throw new ImproperActionException(_('Registration is disabled.'));
    }

    // Stop bot registration by checking if the (invisible to humans) bot input is filled
    if (!empty($Request->request->get('bot'))) {
        throw new IllegalActionException('The bot field was filled on register page. Possible automated registration attempt.');
    }

    // CSRF
    $App->Csrf->validate();

    if ((Tools::checkId((int) $Request->request->get('team')) === false) ||
        !$Request->request->get('firstname') ||
        !$Request->request->get('lastname') ||
        !$Request->request->get('email') ||
        !filter_var($Request->request->get('email'), FILTER_VALIDATE_EMAIL)) {

        throw new ImproperActionException(_('A mandatory field is missing!'));
    }

    // Check whether the query was successful or not
    if (!$App->Users->create(
        $Request->request->get('email'),
        $Request->request->get('team'),
        $Request->request->get('firstname'),
        $Request->request->get('lastname'),
        $Request->request->get('password')
    )) {
        throw new ImproperActionException('Failed inserting new account in SQL!');
    }

    if ($App->Users->needValidation) {
        $Session->getFlashBag()->add('ok', _('Registration successful :)<br>Your account must now be validated by an admin.<br>You will receive an email when it is done.'));
    } else {
        $Session->getFlashBag()->add('ok', _('Registration successful :)<br>Welcome to eLabFTW o/'));
    }
    // store the email here so we can put it in the login field
    $Session->set('email', $Request->request->get('email'));

    // log user creation
    $App->Log->info('New user created');

} catch (Swift_TransportException $e) {
    // for swift error, don't display error to user as it might contain sensitive information
    // but log it and display general error. See #841
    $App->Log->error('', array('exception' => $e));
    $App->Session->getFlashBag()->add('ko', Tools::error());

} catch (ImproperActionException $e) {
    // show message to user
    $App->Session->getFlashBag()->add('ko', $e->__toString());
    $location = '../../register.php';

} catch (IllegalActionException $e) {
    $App->Log->notice('', array(array('userid' => $App->Session->get('userid')), array('IllegalAction', $e->__toString())));
    $App->Session->getFlashBag()->add('ko', Tools::error(true));
    $location = '../../register.php';

} catch (Exception $e) {
    $App->Session->getFlashBag()->add('ko', $e->getMessage());
    $location = '../../register.php';

} finally {
    $Response = new RedirectResponse($location);
    $Response->send();
}
