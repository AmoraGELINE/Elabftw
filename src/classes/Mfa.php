<?php
/**
 * @package   Elabftw\Elabftw
 * @author    Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @license   https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @see       https://www.elabftw.net Official website
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use Elabftw\Services\Filter;
use Elabftw\Services\MpdfQrProvider;
use RobThree\Auth\TwoFactorAuth;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use function time;

/**
 * Provide methods for multi/two-factor authentication
 */
class Mfa
{
    /** @var SessionInterface $Session the current session */
    public $Session;

    /** @var TwoFactorAuth $Mfa */
    private $TwoFactorAuth;

    /** @var Request $Request current request */
    private $Request;

    /** @var Db $Db SQL Database */
    protected $Db;

    /**
     * Constructor
     *
     * @param Request $request
     * @param Session $session
     */
    public function __construct(Request $request, Session $session)
    {
        $this->TwoFactorAuth = new TwoFactorAuth('eLabFTW', 6, 30, 'sha1', new MpdfQrProvider());
        $this->Db = Db::getConnection();
        $this->Request = $request;
        $this->Session = $session;
    }

    /**
     * Generate a new MFA secret
     *
     * @param string $redirect Where do you wan to go after the verification
     * @return void
     */
    public function needVerification(int $userid, string $redirect): void
    {
        $MFASecret = $this->getSecret($userid);
        if (MFASecret && !$App->Session->has('mfa_verified')) {
            $this->Session->set('mfa_secret', $MFASecret);
            $this->Session->set('mfa_redirect', $redirect);

            $Response = new RedirectResponse('../../mfa.php');
            $Response->send();
        }
    }

    /**
     * Does user use two factor authentication?
     *
     * @param int $userid
     * @return mixed MFA secret or false
     */
    public function getSecret(int $userid)
    {
        $sql = 'SELECT mfa_secret FROM users WHERE userid = :userid';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':userid', $userid);
        $this->Db->execute($req);
        $res = $req->fetchColumn();

        if ($res !== null || $res !== false) {
            return (string) $res;
        }
        return false;
    }

    /**
     * Generate a new MFA secret
     *
     * @param string $redirect Where do you wan to go after the verification
     * @return void
     */
    public function enable(string $redirect): void
    {
        // Need to request verification code to confirm user got secret and can authenticate in the future by MFA
        $this->Session->set('mfa_secret', $this->TwoFactorAuth->createSecret(160));
        $this->Session->set('enable_mfa', true);
        $this->Session->set('mfa_redirect', $redirect);

        $Response = new RedirectResponse('../../mfa.php');
        $Response->send();
    }

    /**
     * Save secret in database
     *
     * @return void
     */
    public function saveSecret(): void
    {
        $sql = 'UPDATE users SET mfa_secret = :mfa_secret WHERE userid = :userid';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':mfa_secret', $this->Session->get('mfa_secret'), PDO::PARAM_STR);
        $req->bindParam(':userid', $this->Session->get('userid'), PDO::PARAM_INT);
        $this->Db->execute($req);

        $this->Session->getFlashBag()->add('ok', _('Two Factor Authentication enabled.'));
        $location = $this->Session->get('mfa_redirect');

        $this->Session->remove('mfa_secret');
        $this->Session->remove('enable_mfa');
        $this->Session->remove('mfa_redirect');

        $Response = new RedirectResponse($location);
        $Response->send();
    }

    /**
     * Disable two factor authentication for user
     *
     * @param int $uderid
     * @return void
     */
    public function disable(int $userid): void
    {
        $sql = 'UPDATE users SET mfa_secret = null WHERE userid = :userid';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':userid', $userid, PDO::PARAM_INT);
        $this->Db->execute($req);
    }

    /**
     * Get QR code image with MFA secret as data URI
     *
     * @param string $email
     * @return string
     */
    public function getQRCodeImageAsDataUri(string $email): string
    {
        return $this->TwoFactorAuth->getQRCodeImageAsDataUri($email, $this->Session->get('mfa_secret'));
    }

    /**
     * Verify the MFA code
     *
     * @return bool
     */
    public function verifyCode(): bool
    {
        if ($this->TwoFactorAuth->verifyCode($this->Session->get('mfa_secret'), Filter::sanitize($this->Request->request->get('mfa_code')))) {
            $this->Session->set('mfa_verified', time());
            return true;
        }
        Auth::increaseFailedAttempt();
        $this->Session->getFlashBag()->add('ko', _('Code not verified.'));
        return false;
    }
}
