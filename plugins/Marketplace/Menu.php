<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace;

use Piwik\Db;
use Piwik\Menu\MenuAdmin;
use Piwik\Menu\MenuUser;
use Piwik\Piwik;

/**
 */
class Menu extends \Piwik\Plugin\Menu
{

    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::hasUserSuperUserAccess()) {
            $menu->addManageItem('Marketplace_Marketplace',
                $this->urlForAction('overview', array('activated' => '', 'mode' => 'admin', 'type' => '', 'show' => '')),
                $order = 12);
        }
    }

    public function configureUserMenu(MenuUser $menu)
    {
        if (!Piwik::isUserIsAnonymous()) {
            $menu->addPlatformItem('Marketplace_Marketplace',
                                   $this->urlForAction('overview', array('activated' => '', 'mode' => 'user', 'type' => '', 'show' => '')),
                                   $order = 5);
        }
    }

}
