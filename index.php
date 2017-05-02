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
use OneLogin_Saml2_Auth;

session_start();

try {
    if (isset($_GET['acs'])) {

        require_once 'app/init.inc.php';

        $Saml = new Saml(new Config, new Idps);

        $settings = $Saml->getSettings(2);
        $SamlAuth = new OneLogin_Saml2_Auth($settings);

        if (isset($_SESSION) && isset($_SESSION['AuthNRequestID'])) {
            $requestID = $_SESSION['AuthNRequestID'];
        } else {
            $requestID = null;
        }

        $SamlAuth->processResponse($requestID);

        $errors = $SamlAuth->getErrors();

        if (!empty($errors) && $Saml->Config->configArr['debug']) {
            echo "Something went wrong:<br>";
            echo Tools::printArr(implode(', ', $errors));
        }

        if (!$SamlAuth->isAuthenticated()) {
            throw new Exception("Not authenticated!");
        }

        $Auth = new Auth();
        $_SESSION['samlUserdata'] = $SamlAuth->getAttributes();
        if (!$Auth->loginWithSaml($_SESSION['samlUserdata']['User.email'][0])) {
            // the user doesn't exist yet in the db
            // check if the team exists
            $Teams = new Teams();
            $Users = new Users(null, $Saml->Config);

            $team = $_SESSION['samlUserdata']['memberOf'][0];
            $teamId = $Teams->initializeIfNeeded($team);
            $Users->create($_SESSION['samlUserdata']['User.email'][0], $teamId, $_SESSION['samlUserdata']['User.FirstName'][0], $_SESSION['samlUserdata']['User.LastName'][0]);
            if (!$Auth->loginWithSaml($_SESSION['samlUserdata']['User.email'][0])) {
                throw new Exception("Not authenticated!");
            }
        }

    }
header('Location: experiments.php');

} catch (Exception $e) {
    echo $e->getMessage();
}
