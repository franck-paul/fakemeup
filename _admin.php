<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of Dotclear 2 "Fake Me Up" plugin.
#
# Copyright (c) 2010 Bruno Hondelatte, and contributors.
# Many, many thanks to Olivier Meunier and the Dotclear Team.
# Licensed under the GPL version 2.0 license.
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------
if (!defined('DC_CONTEXT_ADMIN')) { return; }

$_menu['System']->addItem(__('Fake Me Up'),'plugin.php?p=fakemeup','images/check-on.png',
		preg_match('/plugin.php\?p=fakemeup(&.*)?$/',$_SERVER['REQUEST_URI']),
		$core->auth->isSuperAdmin() && is_writable(DC_DIGESTS));
?>
