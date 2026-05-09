<?php

/**
 * This file is part of the hyyan/woo-poly-integration plugin.
 * (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI\Tools;

use Hyyan\WPI\HooksInterface;

/**
 * TranslationsDownloader.
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class TranslationsDownloader
{
    /**
     * Download translation files from woocommerce repo.
     *
     * @global \WP_Filesystem_Base $wp_filesystem
     *
     * @param string $locale locale
     * @param string $name   language name
     *
     * @return bool true when the translation is downloaded successfully
     *
     * @throws \RuntimeException on errors
     */
    public static function download($locale, $name)
    {
        /* Check if already downloaded */
        if (static::isDownloaded($locale)) {
            return true;
        }

        $lock_key = static::acquireDownloadLock($locale, 5 * MINUTE_IN_SECONDS);
        if (!$lock_key) {
            return false;
        }

        $temp_file = null;

        try {

            /* Check if we can download */
            if (!static::isAvaliable($locale)) {
                /* translators: 1: Locale display name with locale code, 2: Repository URL. */
                $notAvaliable = sprintf(
                        __(
                                'WooCommerce translation %1$s can not be found in : <a href="%2$s">%2$s</a>', 'woo-poly-integration'
                        ), sprintf('%s(%s)', $name, $locale), static::getRepoUrl()
                );

                throw new \RuntimeException($notAvaliable);
            }

            /* Download the language pack */
            /* translators: 1: Locale display name with locale code, 2: Repository URL. */
            $cantDownload = sprintf(
                    __('Unable to download WooCommerce translation %1$s from : <a href="%2$s">%2$s</a>', 'woo-poly-integration'), sprintf('%s(%s)', $name, $locale), static::getRepoUrl()
            );

            /* Unzip destination: wp-content/languages/plugins directory */
            $dir = trailingslashit(WP_LANG_DIR) . 'plugins/';
            if (!wp_is_writable($dir)) {
                return false;
            }

            $response = wp_remote_get(
                    sprintf('%s/%s.zip', static::getRepoUrl(), $locale), array('timeout' => 200)
            );

            if (
                    !is_wp_error($response) &&
                    ($response['response']['code'] >= 200 &&
                    $response['response']['code'] < 300)
            ) {

                /* Initialize the WP filesystem, no more using 'file-put-contents' function */
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once ABSPATH.'/wp-admin/includes/file.php';

                    if (false === ($creds = request_filesystem_credentials('', '', false, false, null))) {
                        throw new \RuntimeException($cantDownload);
                    }

                    if (!WP_Filesystem($creds)) {
                        throw new \RuntimeException($cantDownload);
                    }
                }

                $temp_file = wp_tempnam($locale . '.zip');
                if (!$temp_file) {
                    throw new \RuntimeException($cantDownload);
                }

                /* Save the zip file */
                if (!$wp_filesystem->put_contents($temp_file, $response['body'], FS_CHMOD_FILE)) {
                    throw new \RuntimeException($cantDownload);
                }

                $unzip = unzip_file($temp_file, $dir);
                if (is_wp_error($unzip)) {
                    return false;
                }
                if (true !== $unzip) {
                    return false;
                }

                return true;
            } else {
                throw new \RuntimeException($cantDownload);
            }
        } finally {
            static::releaseDownloadLock($lock_key);

            if (isset($temp_file) && is_string($temp_file) && file_exists($temp_file)) {
                @unlink($temp_file);
            }
        }
    }

    /**
     * Acquire translation downloader lock using SQL-backed atomic add_option.
     *
     * @param string $locale      locale
     * @param int    $ttl_seconds lock TTL in seconds
     *
     * @return string|false lock option key on success, false otherwise
     */
    private static function acquireDownloadLock($locale, $ttl_seconds = 300)
    {
        $lock_key = 'wpi_xlate_dl_lock_' . sanitize_key((string) $locale);
        $now = time();
        $expires_at = $now + (int) $ttl_seconds;

        $acquired = add_option($lock_key, $expires_at, '', 'no');
        if ($acquired) {
            return $lock_key;
        }

        $existing = get_option($lock_key);
        if (is_numeric($existing) && (int) $existing < $now) {
            delete_option($lock_key);
            $acquired = add_option($lock_key, $expires_at, '', 'no');
            if ($acquired) {
                return $lock_key;
            }
        }

        return false;
    }

    /**
     * Release translation downloader lock.
     *
     * @param string|false $lock_key lock option key
     *
     * @return void
     */
    private static function releaseDownloadLock($lock_key)
    {
        if ($lock_key) {
            delete_option($lock_key);
        }
    }

    /**
     * Check if the language pack is avaliable in the language repo.
     *
     * @param string $locale locale
     *
     * @return bool true if exists , false otherwise
     */
    public static function isAvaliable($locale)
    {
        $response = wp_remote_get(
                sprintf('%s/%s.zip', static::getRepoUrl(), $locale), array('timeout' => 200)
        );

        if (
                !is_wp_error($response) &&
                ($response['response']['code'] >= 200 &&
                $response['response']['code'] < 300)
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if WooCommerce language file is already downloaded.
     *
     * @param string $locale locale
     *
     * @return bool true if downloded , false otherwise
     */
    public static function isDownloaded($locale)
    {
        $safe_locale_filename = sanitize_file_name((string) $locale);
        if ($safe_locale_filename === '') {
            return false;
        }

        return file_exists(
                sprintf(
                        trailingslashit(WP_LANG_DIR)
                        .'plugins/woocommerce-%s.mo', $safe_locale_filename
                )
        );
    }

    /**
     * Get language repo URL.
     *
     * @return string
     */
    public static function getRepoUrl()
    {
        $url = sprintf(
                'https://downloads.wordpress.org/translation/plugin/woocommerce/%s', WC()->version
        );

        return apply_filters(HooksInterface::LANGUAGE_REPO_URL_FILTER, $url);
    }
}
