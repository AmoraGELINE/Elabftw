<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models\Notifications;

use Elabftw\Interfaces\MailableInterface;
use Elabftw\Models\Config;
use Elabftw\Models\Users;

class UserNeedValidation extends UserCreated implements MailableInterface
{
    protected const CATEGORY = 12;

    protected const PREF = 'notif_user_need_validation';

    public function __construct(private int $userid, private string $team)
    {
        parent::__construct($this->userid, $this->team);
    }

    public function getEmail(): array
    {
        $subject = sprintf(_('[ACTION REQUIRED]') . ' ' . _('New user added to team: %s'), $this->team);
        $user = new Users($this->userid);
        $base = sprintf(
            _('Hi. A new user registered an account on eLabFTW: %s (%s).'),
            $user->userData['fullname'],
            $user->userData['email'],
        );
        $url = Config::fromEnv('SITE_URL') . '/admin.php';
        $body = $base . ' ' . sprintf(_('Head to the admin panel to validate the account: %s'), $url);

        return array(
            'subject' => $subject,
            'body' => $body,
        );
    }
}
