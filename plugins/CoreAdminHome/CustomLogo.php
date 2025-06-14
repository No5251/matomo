<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CoreAdminHome;

use Piwik\Exception\DI\NotFoundException;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Exception\Exception;
use Piwik\Filesystem;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\SettingsPiwik;

class CustomLogo
{
    public const LOGO_HEIGHT = 300;
    public const LOGO_SMALL_HEIGHT = 100;
    public const FAVICON_HEIGHT = 32;

    public const FILENAME_LOGO = 'logo.png';
    public const FILENAME_LOGO_HEADER = 'logo-header.png';
    public const FILENAME_LOGO_SVG = 'logo.svg';
    public const FILENAME_FAVICON = 'favicon.png';

    public function getLogoUrl($pathOnly = false)
    {
        $defaultLogo = 'plugins/Morpheus/images/logo.png';
        $themeLogo = 'plugins/%s/images/logo.png';
        $userLogo = static::getPathUserLogo();
        return $this->getPathToLogo($pathOnly, $defaultLogo, $themeLogo, $userLogo);
    }

    public function getHeaderLogoUrl($pathOnly = false)
    {
        $defaultLogo = 'plugins/Morpheus/images/logo.svg';
        $themeLogo = 'plugins/%s/images/logo-header.png';
        $customLogo = static::getPathUserLogoSmall();
        return $this->getPathToLogo($pathOnly, $defaultLogo, $themeLogo, $customLogo);
    }

    public function getSVGLogoUrl($pathOnly = false)
    {
        $defaultLogo = 'plugins/Morpheus/images/logo.svg';
        $themeLogo = 'plugins/%s/images/logo.svg';
        $customLogo = static::getPathUserSvgLogo();
        $svg = $this->getPathToLogo($pathOnly, $defaultLogo, $themeLogo, $customLogo);
        return $svg;
    }

    public function isEnabled()
    {
        return $this->isCustomLogoFeatureEnabled() && Option::get('branding_use_custom_logo');
    }

    public function enable()
    {
        Option::set('branding_use_custom_logo', '1', true);
    }

    public function disable()
    {
        Option::set('branding_use_custom_logo', '0', true);
    }

    public function hasSVGLogo()
    {
        if (!$this->isEnabled()) {
            /* We always have our application logo */
            return true;
        }

        if ($this->isEnabled() && static::logoExists(static::getPathUserSvgLogo())) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isFileUploadEnabled()
    {
        return ini_get('file_uploads') == 1;
    }

    public function isCustomLogoFeatureEnabled()
    {
        return Config::getInstance()->General['enable_custom_logo'] != 0;
    }

    /**
     * @return bool
     */
    public function isCustomLogoWritable()
    {
        if (Config::getInstance()->General['enable_custom_logo_check'] == 0) {
            return true;
        }
        $pathUserLogo = $this->getPathUserLogo();

        $directoryWritingTo = PIWIK_DOCUMENT_ROOT . '/' . dirname($pathUserLogo);

        // Create directory if not already created
        Filesystem::mkdir($directoryWritingTo);

        $directoryWritable = is_writable($directoryWritingTo);
        $logoFilesWriteable = is_writeable(PIWIK_DOCUMENT_ROOT . '/' . $pathUserLogo)
            && is_writeable(PIWIK_DOCUMENT_ROOT . '/' . $this->getPathUserSvgLogo())
            && is_writeable(PIWIK_DOCUMENT_ROOT . '/' . $this->getPathUserLogoSmall());

        $isCustomLogoWritable = ($logoFilesWriteable || $directoryWritable) && $this->isFileUploadEnabled();

        return $isCustomLogoWritable;
    }

    protected function getPathToLogo($pathOnly, $defaultLogo, $themeLogo, $customLogo)
    {
        $logo = $defaultLogo;

        $theme = \Piwik\Plugin\Manager::getInstance()->getThemeEnabled();
        if (!$theme) {
            $themeName = Manager::DEFAULT_THEME;
        } else {
            $themeName = $theme->getPluginName();
        }
        $themeLogo = sprintf($themeLogo, $themeName);

        if (static::logoExists($themeLogo)) {
            $logo = $themeLogo;
        }
        if ($this->isEnabled() && static::logoExists($customLogo)) {
            $logo = $customLogo;
        }

        if (!$pathOnly) {
            return SettingsPiwik::getPiwikUrl() . $logo;
        }

        return Filesystem::getPathToPiwikRoot() . '/' . $logo;
    }

    private static function getBasePath()
    {
        try {
            $basePath = StaticContainer::get('path.misc.user');
            return $basePath;
        } catch (NotFoundException $e) {
            // happens when upgrading from an older version which didn't have that global config entry yet
            // to a newer version of Matomo when this value is being requested while the update happens
            // basically request starts... the old global.php is loaded, then we update all PHP files, then after the
            // update within the same request a newer version of CustomLogo.php is loaded and they are not compatible.
            // In this case we return the default value
            return 'misc/user/';
        }
    }

    public static function getTempPathUserLogoUploads(): string
    {
        // use sha1 of the username to prevent usage of unsafe characters in the path
        $path = StaticContainer::get('path.tmp') . '/logos/' . sha1(Piwik::getCurrentUserLogin()) . '/';

        if (!is_dir($path)) {
            Filesystem::mkdir($path);
        }

        return $path;
    }

    public static function getPathUserLogo(): string
    {
        return static::rewritePath(self::getBasePath() . self::FILENAME_LOGO);
    }

    public static function getTempPathUserLogo(): string
    {
        return static::getTempPathUserLogoUploads()  . self::FILENAME_LOGO;
    }

    public static function getPathUserFavicon(): string
    {
        return static::rewritePath(self::getBasePath() . self::FILENAME_FAVICON);
    }

    public static function getTempPathUserFavicon(): string
    {
        return static::getTempPathUserLogoUploads()  . self::FILENAME_FAVICON;
    }

    public static function getPathUserSvgLogo(): string
    {
        return static::rewritePath(self::getBasePath() . self::FILENAME_LOGO_SVG);
    }

    public static function getPathUserLogoSmall(): string
    {
        return static::rewritePath(self::getBasePath() . self::FILENAME_LOGO_HEADER);
    }

    public static function getTempPathUserLogoSmall(): string
    {
        return static::getTempPathUserLogoUploads() . self::FILENAME_LOGO_HEADER;
    }

    protected static function rewritePath(string $path): string
    {
        return SettingsPiwik::rewriteMiscUserPathWithInstanceId($path);
    }

    public static function hasTempLogo(): bool
    {
        $logoTempPath = static::getTempPathUserLogo();
        $smallLogoTempPath = static::getTempPathUserLogoSmall();

        return (file_exists($logoTempPath) && file_exists($smallLogoTempPath));
    }

    public static function hasTempFavicon(): bool
    {
        $faviconTempPath = static::getTempPathUserFavicon();

        return file_exists($faviconTempPath);
    }

    /**
     * @return bool
     */
    public static function hasUserLogo()
    {
        return static::logoExists(static::getPathUserLogo());
    }

    /**
     * @return bool
     */
    public static function hasUserFavicon()
    {
        return static::logoExists(static::getPathUserFavicon());
    }

    private function postLogoChangeEvent($imagePath): void
    {
        $rootPath = Filesystem::getPathToPiwikRoot();
        $absolutePath = $rootPath . '/' . $imagePath;

        /**
         * Triggered when a user uploads a custom logo. This event is triggered for
         * the large logo, for the smaller logo-header.png file, and for the favicon.
         *
         * @param string $absolutePath The absolute path to the logo file on the Piwik server.
         */
        Piwik::postEvent('CoreAdminHome.customLogoChanged', [$absolutePath]);
    }

    public function uploadFaviconToTempFolder(): bool
    {
        $uploadFieldName = 'customFavicon';

        $faviconTempPath = static::getTempPathUserFavicon();

        return $this->uploadImage($uploadFieldName, self::FAVICON_HEIGHT, $faviconTempPath);
    }

    public function uploadLogoToTempFolder(): bool
    {
        $uploadFieldName = 'customLogo';

        $logoTempPath = static::getTempPathUserLogo();
        $smallLogoTempPath = static::getTempPathUserLogoSmall();

        $success = $this->uploadImage($uploadFieldName, self::LOGO_SMALL_HEIGHT, $smallLogoTempPath);
        if (!$success) {
            return false;
        }

        $success = $this->uploadImage($uploadFieldName, self::LOGO_HEIGHT, $logoTempPath);
        if (!$success) {
            return false;
        }

        return true;
    }

    /**
     * Publish logo and small logo from tmp folder to user folder
     *
     * @return bool
     */
    public function publishUserLogo(): bool
    {
        $logoTempPath = static::getTempPathUserLogo();
        $logoUserPath = static::getPathUserLogo();

        $smallLogoTempPath = static::getTempPathUserLogoSmall();
        $smallLogoUserPath = static::getPathUserLogoSmall();

        try {
            if (file_exists($logoTempPath) && file_exists($smallLogoTempPath)) {
                Filesystem::copy($logoTempPath, $logoUserPath);
                Filesystem::copy($smallLogoTempPath, $smallLogoUserPath);

                $this->postLogoChangeEvent($logoUserPath);
                $this->postLogoChangeEvent($smallLogoUserPath);

                // remove temp files
                Filesystem::remove($logoTempPath);
                Filesystem::remove($smallLogoTempPath);

                return true;
            }
        } catch (Exception $e) {
            // nop
        }

        return false;
    }

    /**
     * Publish favicon from tmp folder to user folder
     *
     * @return bool
     */
    public function publishUserFavicon(): bool
    {
        $faviconTempPath = static::getTempPathUserFavicon();
        $faviconUserPath = static::getPathUserFavicon();

        try {
            if (file_exists($faviconTempPath)) {
                Filesystem::copy($faviconTempPath, $faviconUserPath);

                $this->postLogoChangeEvent($faviconUserPath);

                // remove temp file
                Filesystem::remove($faviconTempPath);

                return true;
            }
        } catch (Exception $e) {
            // nop
        }

        return false;
    }

    /**
     * Remove any uploaded logos from tmp and user folders
     *
     * @return void
     */
    public function removeLogos(): void
    {
        static::removePublishedLogos();
        static::removeLogosFromTempFolder();
    }

    /**
     * Remove publicly accessible logos and favicons from the misc/user folder
     *
     * @return void
     */
    public function removePublishedLogos(): void
    {
        $logoUserPath = static::getPathUserLogo();
        $smallLogoUserPath = static::getPathUserLogoSmall();
        $faviconUserPath = static::getPathUserFavicon();

        Filesystem::deleteFileIfExists($logoUserPath);
        Filesystem::deleteFileIfExists($smallLogoUserPath);
        Filesystem::deleteFileIfExists($faviconUserPath);
    }

    /**
     * Remove all uploaded logos and favicons from the temp folder
     *
     * @return void
     */
    public function removeLogosFromTempFolder(): void
    {
        $logosUploadTempFolder = static::getTempPathUserLogoUploads();
        Filesystem::unlinkRecursive($logosUploadTempFolder, true);
    }

    /**
     * Process logo/favicon uploads from the request and store in a given path
     * @param $uploadFieldName
     * @param $targetHeight
     * @param $path
     * @return bool
     */
    private function uploadImage($uploadFieldName, $targetHeight, $path): bool
    {
        if (
            empty($_FILES[$uploadFieldName])
            || !empty($_FILES[$uploadFieldName]['error'])
        ) {
            return false;
        }

        $file = $_FILES[$uploadFieldName]['tmp_name'];
        if (!file_exists($file)) {
            return false;
        }

        list($width, $height) = getimagesize($file);
        switch ($_FILES[$uploadFieldName]['type']) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($file);
                break;
            default:
                return false;
        }

        // @phpstan-ignore class.notFound
        if (!is_resource($image) && !($image instanceof \GdImage)) {
            return false;
        }

        $targetWidth = round($width * $targetHeight / $height);

        $newImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($_FILES[$uploadFieldName]['type'] == 'image/png') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        $backgroundColor = imagecolorallocate($newImage, 0, 0, 0);
        imagecolortransparent($newImage, $backgroundColor);

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        return imagepng($newImage, $path, 3);
    }

    /**
     * @return bool
     */
    private static function logoExists($relativePath)
    {
        return file_exists(Filesystem::getPathToPiwikRoot() . '/' . $relativePath);
    }

    /**
     * If tmp logo exists, return it as base64 encoded string for preview in branding settings
     *
     * @return string|null
     */
    public function getTempUserLogoBase64(): ?string
    {
        $img = static::getTempPathUserLogo();

        if (file_exists($img)) {
            return base64_encode(file_get_contents($img));
        }

        return null;
    }

    /**
     * If tmp favicon exists, return it as base64 encoded string for preview in branding settings
     *
     * @return string|null
     */
    public function getTempUserFaviconBase64(): ?string
    {
        $img = static::getTempPathUserFavicon();

        if (file_exists($img)) {
            return base64_encode(file_get_contents($img));
        }

        return null;
    }
}
