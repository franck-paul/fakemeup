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
declare(strict_types=1);

if (isset($this) && is_object($this) && method_exists($this, 'registerModule') && isset($this->id) && is_string($this->id)) {
    $this->registerModule(
        'Fake Me Up',
        'Fakes Dotclear digest to force automatic updates',
        'Bruno Hondelatte',
        '7.1',
        [
            'date'     => '2026-11-07T16:50:54+0100',
            'requires' => [['core', '2.39']],
            'type'     => 'plugin',

            'details'    => 'https://open-time.net/?q=fakemup',
            'support'    => 'https://github.com/franck-paul/fakemup',
            'repository' => 'https://raw.githubusercontent.com/franck-paul/fakemeup/main/dcstore.xml',
            'license'    => 'gpl2',
        ]
    );
}
