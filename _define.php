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
$this->registerModule(
    'Fake Me Up',
    'Fakes Dotclear digest to force automatic updates',
    'Bruno Hondelatte',
    '5.0.2',
    [
        'requires' => [['core', '2.29']],
        'type'     => 'plugin',

        'details'    => 'https://open-time.net/?q=fakemup',
        'support'    => 'https://github.com/franck-paul/fakemup',
        'repository' => 'https://raw.githubusercontent.com/franck-paul/fakemeup/main/dcstore.xml',
    ]
);
