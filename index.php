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

class adminFakeMeUp
{
    // Constants
    private const DC_DIGESTS_BACKUP = DC_ROOT . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'digests.bak';

    /**
     * Initializes the page.
     */
    public static function init()
    {
        // Super admin only
        dcPage::checkSuper();

        dcCore::app()->admin->changes = [
            'same'    => [],
            'changed' => [],
            'removed' => [],
        ];
        dcCore::app()->admin->helpus = l10n::getFilePath(dirname(__FILE__) . '/locales', 'helpus.html', dcCore::app()->lang);
    }

    /**
     * Processes the request(s).
     */
    public static function process()
    {
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
                dcCore::app()->admin->changes = $changes;

                $digest = '';
                foreach ($arr as $k => $v) {
                    $digest .= sprintf("%s  %s\n", $v, $k);
                }
                rename(DC_DIGESTS, self::DC_DIGESTS_BACKUP);
                file_put_contents(DC_DIGESTS, $digest);
                dcCore::app()->admin->uri = self::backup(dcCore::app()->admin->changes);
            } elseif (isset($_POST['disclaimer_ok'])) {
                dcCore::app()->admin->changes = self::check(DC_ROOT, DC_DIGESTS);
            }
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }
    }

    /**
     * Renders the page.
     */
    public static function render()
    {
        echo
        '<html>' .
        '<head><title>' . __('Fake Me Up') . '</title>' .
        '</head>' .
        '<body>' .
        dcPage::breadcrumb(
            [
                html::escapeHTML(dcCore::app()->blog->name) => '',
                __('Fake Me Up')                            => '',
            ]
        ) .
        dcPage::notices();

        if (!dcCore::app()->error->flag()) {
            if (isset($_POST['override'])) {
                echo
                '<div class="message">';
                if (dcCore::app()->admin->uri !== false) {
                    printf(file_get_contents(dcCore::app()->admin->helpus), dcCore::app()->admin->uri, 'fakemeup@dotclear.org');
                } else {
                    echo
                    '<p>' . __('The updates have been performed.') . '</p>';
                }
                echo
                '<p><a href="update.php">' . __('Update Dotclear') . '</a></p>' .
                '</div>';
            } elseif (isset($_POST['disclaimer_ok'])) {
                if (count(dcCore::app()->admin->changes['changed']) == 0 && count(dcCore::app()->admin->changes['removed']) == 0) {
                    echo
                    '<p class="message">' . __('No changed filed have been found, nothing to do!') . '</p>';
                } else {
                    echo
                    '<div class="message">';
                    if (count(dcCore::app()->admin->changes['changed']) != 0) {
                        echo
                        '<p>' . __('The following files will have their checksum faked:') . '</p>' .
                        '<ul>';
                        foreach (dcCore::app()->admin->changes['changed'] as $k => $v) {
                            printf('<li> %s [old:%s, new:%s]</li>', $k, $v['old'], $v['new']);
                        }
                        echo
                        '</ul>';
                    }
                    if (count(dcCore::app()->admin->changes['removed']) != 0) {
                        echo
                        '<p>' . __('The following files digests will have their checksum cleaned:') . '</p>' .
                        '<ul>';
                        foreach (dcCore::app()->admin->changes['removed'] as $k => $v) {
                            printf('<li> %s</li>', $k);
                        }
                        echo
                        '</ul>';
                    }
                    echo
                    '<form action="' . dcCore::app()->admin->getPageURL() . '" method="post"><p>' .
                    dcCore::app()->formNonce() .
                    form::hidden('override', 1) .
                    '<input type="submit" name="confirm" value="' . __('Still ok to continue') . '"/></p></form></div>';
                }
            } else {
                if (file_exists(self::DC_DIGESTS_BACKUP)) {
                    echo
                    '<div class="error"><p>' . __('Fake Me Up has already been run once.') . '</p>' .
                    '<form action="' . dcCore::app()->admin->getPageURL() . '" method="post">' .
                    '<p><input type="checkbox" name="erase_backup" id="erase_backup" class="classic" />&nbsp;' .
                    '<label for="erase_backup" class="classic">' . __('Remove the backup digest file, I want to play again') . '</label>' .
                    dcCore::app()->formNonce() .
                    '</p>' .
                    '<p><input type="submit" name="confirm" id="confirm" value="' . __('Continue') . '"/></p>' .
                    '</form></div>';
                } else {
                    $disclaimer = l10n::getFilePath(dirname(__FILE__) . '/locales', 'disclaimer.html', dcCore::app()->lang);
                    echo
                    '<p class="error">' . __('Please read carefully the following disclaimer before proceeding!') . '</p>' .
                    '<div class="message">' . file_get_contents($disclaimer) .
                    '<form action="' . dcCore::app()->admin->getPageURL() . '" method="post">' .
                    '<p><input type="checkbox" name="disclaimer_ok" id="disclaimer_ok" />&nbsp;' .
                    '<label for="disclaimer_ok" class="classic">' . __('I have read and understood the disclaimer and wish to continue anyway.') . '</label>' .
                    dcCore::app()->formNonce() .
                    '</p>' .
                    '<p><input type="submit" name="confirm" id="confirm" value="' . __('Continue') . '"/></p>' .
                    '</form></div>';
                }
            }
        }
        echo
        '</body>' .
        '</html>';
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
    private function backup(array $changes)
    {
        if (preg_match('#^http(s)?://#', dcCore::app()->blog->settings->system->public_url)) {
            $public_root = dcCore::app()->blog->settings->system->public_url;
        } else {
            $public_root = dcCore::app()->blog->host . path::clean(dcCore::app()->blog->settings->system->public_url);
        }
        $zip_name      = sprintf('fmu_backup_%s.zip', date('YmdHis'));
        $zip_file      = sprintf('%s/%s', dcCore::app()->blog->public_path, $zip_name);
        $zip_uri       = sprintf('%s/%s', $public_root, $zip_name);
        $checksum_file = sprintf('%s/fmu_checksum_%s.txt', dcCore::app()->blog->public_path, date('Ymd'));

        $c_data = 'Fake Me Up Checksum file - ' . date('d/m/Y H:i:s') . "\n\n" .
            'Dotclear version : ' . DC_VERSION . "\n\n";
        if (count($changes['removed'])) {
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
        $b_zip = new fileZip($b_fp);
        if (count($changes['changed'])) {
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

adminFakeMeUp::init();
adminFakeMeUp::process();
adminFakeMeUp::render();
