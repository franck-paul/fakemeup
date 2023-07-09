<?php
/**
 * @brief fakemeup, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\fakemeup;

use dcCore;
use dcNsProcess;
use dcPage;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Zip;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\L10n;
use Exception;

class Manage extends dcNsProcess
{
    // Constants
    private const DC_DIGESTS_BACKUP = DC_ROOT . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'digests.bak';

    // Properties
    private static array $changes = [];
    private static string $helpus = '';
    private static $uri           = '';
    protected static $init        = false; /** @deprecated since 2.27 */

    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        static::$init = My::checkContext(My::MANAGE);

        return static::$init;
    }

    /**
     * Processes the request(s).
     */
    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        self::$changes = [
            'same'    => [],
            'changed' => [],
            'removed' => [],
        ];
        self::$helpus = L10n::getFilePath(My::path() . '/locales', 'helpus.html', dcCore::app()->lang) ?: '';

        if (isset($_POST['erase_backup'])) {
            @unlink(self::DC_DIGESTS_BACKUP);
        }

        try {
            if (isset($_POST['override'])) {
                $changes = self::check(DC_ROOT, DC_DIGESTS);
                $arr     = $changes['same'];
                foreach ($changes['changed'] as $k => $v) {
                    $arr[$k] = $v['new'];
                }
                ksort($arr);
                self::$changes = $changes;

                $digest = '';
                foreach ($arr as $k => $v) {
                    $digest .= sprintf("%s  %s\n", $v, $k);
                }
                rename(DC_DIGESTS, self::DC_DIGESTS_BACKUP);
                file_put_contents(DC_DIGESTS, $digest);
                self::$uri = self::backup(self::$changes);
            } elseif (isset($_POST['disclaimer_ok'])) {
                self::$changes = self::check(DC_ROOT, DC_DIGESTS);
            }
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!static::$init) {
            return;
        }

        dcPage::openModule(__('Fake Me Up'));

        echo dcPage::breadcrumb(
            [
                __('System')     => '',
                __('Fake Me Up') => '',
            ]
        );
        echo dcPage::notices();

        // Form
        if (!dcCore::app()->error->flag()) {
            if (isset($_POST['override'])) {
                if (self::$uri !== false) {
                    $item = (new Text(null, sprintf(file_get_contents(self::$helpus), self::$uri, 'fakemeup@dotclear.org')));
                } else {
                    $item = (new Para())->items([
                        (new Text(null, __('The updates have been performed.'))),
                    ]);
                }
                echo (new Div())
                    ->class('message')
                    ->items([
                        $item,
                        (new Para())->items([
                            (new Link())
                                ->href(dcCore::app()->adminurl->get('admin.update'))
                                ->text(__('Update Dotclear')),
                        ]),
                    ])
                ->render();
            } elseif (isset($_POST['disclaimer_ok'])) {
                if ((is_countable(self::$changes['changed']) ? count(self::$changes['changed']) : 0) == 0 && (is_countable(self::$changes['removed']) ? count(self::$changes['removed']) : 0) == 0) {
                    echo (new Para())->class('message')->items([
                        (new Text(null, __('No changed filed have been found, nothing to do!'))),
                    ])
                    ->render();
                } else {
                    $changed       = [];
                    $block_changed = '';
                    if ((is_countable(self::$changes['changed']) ? count(self::$changes['changed']) : 0) != 0) {
                        foreach (self::$changes['changed'] as $k => $v) {
                            $changed[] = (new Text('li', sprintf('%s [old:%s, new:%s]', $k, $v['old'], $v['new'])));
                        }
                        $block_changed = (new Div())->class('message')->items([
                            (new Para())->items([
                                (new Text(null, __('The following files will have their checksum faked:'))),
                            ]),
                            (new Para(null, 'ul'))->items($changed),
                        ])
                        ->render();
                    }
                    $removed       = [];
                    $block_removed = '';
                    if ((is_countable(self::$changes['removed']) ? count(self::$changes['removed']) : 0) != 0) {
                        foreach (self::$changes['removed'] as $k => $v) {
                            $removed[] = (new Text('li', $k));
                        }
                        $block_removed = (new Div())->class('message')->items([
                            (new Para())->items([
                                (new Text(null, __('The following files digests will have their checksum cleaned:'))),
                            ]),
                            (new Para(null, 'ul'))->items($removed),
                        ])
                        ->render();
                    }

                    echo (new Div())->class('message')->items([
                        (new Text(null, $block_changed)),
                        (new Text(null, $block_removed)),
                        (new Form('frm-override'))
                            ->action(dcCore::app()->admin->getPageURL())
                            ->method('post')
                            ->fields([
                                (new Submit(['confirm'], __('Still ok to continue'))),
                                (new Hidden(['override'], (string) 1)),
                                dcCore::app()->formNonce(false),
                            ]),
                    ])
                    ->render();
                }
            } else {
                if (file_exists(self::DC_DIGESTS_BACKUP)) {
                    echo (new Div())->class('error')->items([
                        (new Para())->items([
                            (new Text(null, __('Fake Me Up has already been run once.'))),
                        ]),
                        (new Form('frm-erase'))
                            ->action(dcCore::app()->admin->getPageURL())
                            ->method('post')
                            ->fields([
                                (new Para())->items([
                                    (new Checkbox('erase_backup'))
                                        ->value(1)
                                        ->label((new Label(__('Remove the backup digest file, I want to play again'), Label::INSIDE_TEXT_AFTER))),
                                ]),
                                (new Para())->items([
                                    (new Submit(['confirm'], __('Continue'))),
                                    dcCore::app()->formNonce(false),
                                ]),
                            ]),
                    ])
                    ->render();
                } else {
                    $disclaimer = L10n::getFilePath(My::path() . '/locales', 'disclaimer.html', dcCore::app()->lang);
                    echo (new Para())->class('error')->items([
                        (new Text(null, __('Please read carefully the following disclaimer before proceeding!'))),
                    ])
                    ->render();
                    echo (new Div())->class('message')->items([
                        (new Text(null, file_get_contents($disclaimer))),
                        (new Form('frm-disclaimer'))
                            ->action(dcCore::app()->admin->getPageURL())
                            ->method('post')
                            ->fields([
                                (new Para())->items([
                                    (new Checkbox('disclaimer_ok'))
                                        ->value(1)
                                        ->label((new Label(__('I have read and understood the disclaimer and wish to continue anyway.'), Label::INSIDE_TEXT_AFTER))),
                                ]),
                                (new Para())->items([
                                    (new Submit(['confirm'], __('Continue'))),
                                    dcCore::app()->formNonce(false),
                                ]),
                            ]),
                    ])
                    ->render();
                }
            }
        }

        dcPage::closeModule();
    }

    // Private helper methods

    /**
     * Check digest file
     *
     * @param      string     $root          The root
     * @param      string     $digests_file  The digests file
     *
     * @throws     Exception
     *
     * @return     array
     */
    private static function check(string $root, string $digests_file): array
    {
        if (!is_readable($digests_file)) {
            throw new Exception(__('Unable to read digests file.'));
        }

        $opts     = FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES;
        $contents = file($digests_file, $opts);

        $changed = [];
        $same    = [];
        $removed = [];

        foreach ($contents as $digest) {
            if (!preg_match('#^([\da-f]{32})\s+(.+?)$#', $digest, $m)) {
                continue;
            }

            $md5      = $m[1];
            $filename = $root . '/' . $m[2];

            # Invalid checksum
            if (is_readable($filename)) {
                $md5_new = md5_file($filename);
                if ($md5 == $md5_new) {
                    $same[$m[2]] = $md5;
                } else {
                    $changed[$m[2]] = ['old' => $md5,'new' => $md5_new];
                }
            } else {
                $removed[$m[2]] = true;
            }
        }

        # No checksum found in digests file
        if (empty($md5)) {
            throw new Exception(__('Invalid digests file.'));
        }

        return [
            'same'    => $same,
            'changed' => $changed,
            'removed' => $removed,
        ];
    }

    /**
     * Backup digest
     *
     * @param      array        $changes  The changes
     *
     * @return     bool|string  False on error, zip URI on success
     */
    private static function backup(array $changes)
    {
        if (preg_match('#^http(s)?://#', (string) dcCore::app()->blog->settings->system->public_url)) {
            $public_root = dcCore::app()->blog->settings->system->public_url;
        } else {
            $public_root = dcCore::app()->blog->host . Path::clean(dcCore::app()->blog->settings->system->public_url);
        }
        $zip_name      = sprintf('fmu_backup_%s.zip', date('YmdHis'));
        $zip_file      = sprintf('%s/%s', dcCore::app()->blog->public_path, $zip_name);
        $zip_uri       = sprintf('%s/%s', $public_root, $zip_name);
        $checksum_file = sprintf('%s/fmu_checksum_%s.txt', dcCore::app()->blog->public_path, date('Ymd'));

        $c_data = 'Fake Me Up Checksum file - ' . date('d/m/Y H:i:s') . "\n\n" .
            'Dotclear version : ' . DC_VERSION . "\n\n";
        if (is_countable($changes['removed']) ? count($changes['removed']) : 0) {
            $c_data .= "== Removed files ==\n";
            foreach ($changes['removed'] as $k => $v) {
                $c_data .= sprintf(" * %s\n", $k);
            }
            $c_data .= "\n";
        }
        if (file_exists($zip_file)) {
            @unlink($zip_file);
        }

        $b_fp = @fopen($zip_file, 'wb');
        if ($b_fp === false) {
            return false;
        }
        $b_zip = new Zip($b_fp);

        if (is_countable($changes['changed']) ? count($changes['changed']) : 0) {
            $c_data .= "== Invalid checksum files ==\n";
            foreach ($changes['changed'] as $k => $v) {
                $name = substr($k, 2);
                $c_data .= sprintf(" * %s [expected: %s ; current: %s]\n", $k, $v['old'], $v['new']);

                try {
                    $b_zip->addFile(DC_ROOT . '/' . $name, $name);
                } catch (Exception $e) {
                    $c_data .= $e->getMessage();
                }
            }
        }
        file_put_contents($checksum_file, $c_data);
        $b_zip->addFile($checksum_file, basename($checksum_file));

        $b_zip->write();
        fclose($b_fp);
        $b_zip->close();

        @unlink($checksum_file);

        return $zip_uri;
    }
}
