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

use Dotclear\App;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Process;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Zip;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\L10n;
use Exception;

class Manage extends Process
{
    // Properties

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $changes = [];

    private static string $helpus = '';

    private static string|bool $uri = '';

    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        return self::status(My::checkContext(My::MANAGE));
    }

    /**
     * Processes the request(s).
     */
    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        self::$changes = [
            'same'    => [],
            'changed' => [],
            'removed' => [],
        ];
        self::$helpus = L10n::getFilePath(My::path() . '/locales', 'helpus.html', App::lang()->getLang()) ?: '';

        $backup = App::config()->dotclearRoot() . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'digests.bak';

        if (isset($_POST['erase_backup'])) {
            @unlink($backup);
        }

        try {
            if (isset($_POST['override'])) {
                $changes = self::check(App::config()->dotclearRoot(), App::config()->digestsRoot());
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

                rename(App::config()->digestsRoot(), $backup);
                file_put_contents(App::config()->digestsRoot(), $digest);
                self::$uri = self::backup(self::$changes);
            } elseif (isset($_POST['disclaimer_ok'])) {
                self::$changes = self::check(App::config()->dotclearRoot(), App::config()->digestsRoot());
            }
        } catch (Exception $exception) {
            App::error()->add($exception->getMessage());
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        Page::openModule(My::name());

        echo Page::breadcrumb(
            [
                __('System')     => '',
                __('Fake Me Up') => '',
            ]
        );
        echo Notices::getNotices();

        $backup = App::config()->dotclearRoot() . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'digests.bak';

        // Form
        if (!App::error()->flag()) {
            if (isset($_POST['override'])) {
                if (self::$uri !== false) {
                    $item = (new Text(null, sprintf((string) file_get_contents(self::$helpus), self::$uri, 'fakemeup@dotclear.org')));
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
                                ->href(App::backend()->url()->get('admin.update'))
                                ->text(__('Update Dotclear')),
                        ]),
                    ])
                ->render();
            } elseif (isset($_POST['disclaimer_ok'])) {
                if (self::$changes['changed'] === [] && self::$changes['removed'] === []) {
                    echo (new Para())->class('message')->items([
                        (new Text(null, __('No changed filed have been found, nothing to do!'))),
                    ])
                    ->render();
                } else {
                    $changed       = [];
                    $block_changed = '';
                    if (self::$changes['changed'] !== []) {
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
                    if (self::$changes['removed'] !== []) {
                        foreach (array_keys(self::$changes['removed']) as $k) {
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
                            ->action(App::backend()->getPageURL())
                            ->method('post')
                            ->fields([
                                (new Submit(['confirm'], __('Still ok to continue'))),
                                ... My::hiddenFields([
                                    'override' => (string) 1,
                                ]),
                            ]),
                    ])
                    ->render();
                }
            } elseif (file_exists($backup)) {
                echo (new Div())->class('error')->items([
                    (new Para())->items([
                        (new Text(null, __('Fake Me Up has already been run once.'))),
                    ]),
                    (new Form('frm-erase'))
                        ->action(App::backend()->getPageURL())
                        ->method('post')
                        ->fields([
                            (new Para())->items([
                                (new Checkbox('erase_backup'))
                                    ->value(1)
                                    ->label((new Label(__('Remove the backup digest file, I want to play again'), Label::INSIDE_TEXT_AFTER))),
                            ]),
                            (new Para())->items([
                                (new Submit(['confirm'], __('Continue'))),
                                ... My::hiddenFields(),
                            ]),
                        ]),
                ])
                ->render();
            } else {
                $disclaimer = L10n::getFilePath(My::path() . '/locales', 'disclaimer.html', App::lang()->getLang());
                echo (new Para())->class('error')->items([
                    (new Text(null, __('Please read carefully the following disclaimer before proceeding!'))),
                ])
                ->render();
                echo (new Div())->class('message')->items([
                    (new Text(null, (string) file_get_contents((string) $disclaimer))),
                    (new Form('frm-disclaimer'))
                        ->action(App::backend()->getPageURL())
                        ->method('post')
                        ->fields([
                            (new Para())->items([
                                (new Checkbox('disclaimer_ok'))
                                    ->value(1)
                                    ->label((new Label(__('I have read and understood the disclaimer and wish to continue anyway.'), Label::INSIDE_TEXT_AFTER))),
                            ]),
                            (new Para())->items([
                                (new Submit(['confirm'], __('Continue'))),
                                ... My::hiddenFields(),
                            ]),
                        ]),
                ])
                ->render();
            }
        }

        Page::closeModule();
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
     * @return     array<string, mixed>
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

        if ($contents !== false) {
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
     * @param      array<string, mixed>        $changes  The changes
     *
     * @return     bool|string  False on error, zip URI on success
     */
    private static function backup(array $changes): bool|string
    {
        if (preg_match('#^http(s)?://#', (string) App::blog()->settings()->system->public_url)) {
            $public_root = App::blog()->settings()->system->public_url;
        } else {
            $public_root = App::blog()->host() . Path::clean(App::blog()->settings()->system->public_url);
        }

        $zip_name      = sprintf('fmu_backup_%s.zip', date('YmdHis'));
        $zip_file      = sprintf('%s/%s', App::blog()->publicPath(), $zip_name);
        $zip_uri       = sprintf('%s/%s', $public_root, $zip_name);
        $checksum_file = sprintf('%s/fmu_checksum_%s.txt', App::blog()->publicPath(), date('Ymd'));

        $c_data = 'Fake Me Up Checksum file - ' . date('d/m/Y H:i:s') . "\n\n" .
            'Dotclear version : ' . App::config()->dotclearVersion() . "\n\n";
        if ((is_countable($changes['removed']) ? count($changes['removed']) : 0) !== 0) {
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

        if ((is_countable($changes['changed']) ? count($changes['changed']) : 0) !== 0) {
            $c_data .= "== Invalid checksum files ==\n";
            foreach ($changes['changed'] as $k => $v) {
                $name = substr($k, 2);
                $c_data .= sprintf(" * %s [expected: %s ; current: %s]\n", $k, $v['old'], $v['new']);

                try {
                    $b_zip->addFile(App::config()->dotclearRoot() . '/' . $name, $name);
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
