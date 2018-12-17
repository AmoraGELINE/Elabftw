<?php
/**
 * index.php
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use Exception;
use OneLogin\Saml2\Auth as SamlAuth;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

try {
    require_once 'app/init.inc.php';

    $Response = new RedirectResponse("experiments.php");

    if ($Request->query->has('acs')) {

        $Saml = new Saml(new Config, new Idps);

        // TODO this is the id of the idp to use to get the settings
        $settings = $Saml->getSettings(1);
        $SamlAuth = new SamlAuth($settings);

        $requestID = null;
        if ($Session->has('AuthNRequestID')) {
            $requestID = $Session->get('AuthNRequestID');
        }

        $SamlAuth->processResponse($requestID);

        $errors = $SamlAuth->getErrors();

        if (!empty($errors) && $Saml->Config->configArr['debug']) {
            echo 'Something went wrong:<br>';
            echo Tools::printArr($errors);
        }

        if (!$SamlAuth->isAuthenticated()) {
            throw new Exception('Not authenticated!');
        }

        $Session->set('samlUserdata', $SamlAuth->getAttributes());

        // GET EMAIL
        $emailAttribute = $Saml->Config->configArr['saml_email'];
        $email = $Session->get('samlUserdata')[$emailAttribute];
        if (is_array($email)) {
            $email = $email[0];
        }

        if ($email === null) {
            throw new Exception("Could not find email in response from IDP! Aborting.");
        }

        if (!$App->Users->Auth->loginFromSaml($email)) {
            // the user doesn't exist yet in the db
            // check if the team exists
            $Teams = new Teams($App->Users);

            // GET TEAM
            $teamAttribute = $Saml->Config->configArr['saml_team'];
            // we didn't receive any team attribute for some reason
            if (empty($teamAttribute)) {
                throw new Exception('Team attribute is empty!');
            }
            $team = $Session->get('samlUserdata')[$teamAttribute];
            if (is_array($team)) {
                $team = $team[0];
            }
            $teamId = $Teams->initializeIfNeeded($team);

            // GET FIRSTNAME AND LASTNAME
            $firstnameAttribute = $Saml->Config->configArr['saml_firstname'];
            $firstname = $Session->get('samlUserdata')[$firstnameAttribute];
            if (is_array($firstname)) {
                $firstname = $firstname[0];
            }
            $lastnameAttribute = $Saml->Config->configArr['saml_lastname'];
            $lastname = $Session->get('samlUserdata')[$lastnameAttribute];
            if (is_array($lastname)) {
                $lastname = $lastname[0];
            }

            // CREATE USER
            $App->Users->create($email, $teamId, $firstname, $lastname);
            // ok now the user is created, try logging in again
            if (!$App->Users->Auth->loginFromSaml($email)) {
                throw new Exception("Not authenticated!");
            }
        }

    }

} catch (Exception $e) {
    $template = 'error.html';
    $renderArr = array('error' => $e->getMessage());
    $Response = new Response();
    $Response->prepare($Request);
    $Response->setContent($App->render($template, $renderArr));

} finally {
    $Response->send();
}
