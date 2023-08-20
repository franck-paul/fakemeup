<?php
/**
 * @brief fakemeup, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Jean-Christian Denis, Franck Paul and contributors
 *
 * @copyright Jean-Christian Denis, Franck Paul
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\fakemeup;

use dcCore;
use Dotclear\Module\MyPlugin;

/**
 * Plugin definitions
 */
class My extends MyPlugin
{
    /**
     * Check permission depending on given context
     *
     * @param      int   $context  The context
     *
     * @return     bool  true if allowed, else false
     */
    public static function checkCustomContext(int $context): ?bool
    {
        return match ($context) {
            self::MANAGE,
            self::CONFIG,
            self::MENU,
            self::WIDGETS => defined('DC_CONTEXT_ADMIN') && dcCore::app()->auth->isSuperAdmin(),   // Super-admin only

            default => null
        };
    }

    /**
     * Return URL regexp scheme cope by the plugin
     *
     * @return     string
     */
    public static function urlScheme(): string
    {
        return '/' . preg_quote(dcCore::app()->admin->url->get('admin.plugin.' . self::id())) . '(&.*)?$/';
    }
}
