<?php
/**
 * @brief Fake Me Up, an upgrade helper plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Bruno Hondelatte, and contributors
 *
 * @copyright Bruno Hondelatte
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

dcCore::app()->menu[dcAdmin::MENU_SYSTEM]->addItem(
    __('Fake Me Up'),
    dcCore::app()->adminurl->get('admin.plugin.fakemeup'),
    [urldecode(dcPage::getPF('fakemeup/icon.svg')), urldecode(dcPage::getPF('fakemeup/icon-dark.svg'))],
    preg_match('/' . preg_quote(dcCore::app()->adminurl->get('admin.plugin.fakemeup')) . '(&.*)?$/', $_SERVER['REQUEST_URI']),
    dcCore::app()->auth->isSuperAdmin() && is_writable(DC_DIGESTS)
);
