<?php

class Danslo_Aop_Model_Observer extends Mage_Core_Model_Observer
{
    /**
     * The AOP cache directory.
     */
    const AOP_CACHE_DIR = 'aop';

    /**
     * The AOP cache type.
     */
    const AOP_CACHE_TYPE = 'aop';

    /**
     * Whether or not our autoloader has already been registered.
     *
     * @var boolean
     */
    public static $registered = false;

    /**
     * Whether or not we have initialized our aspect kernel.
     *
     * @var boolean
     */
    public static $initialized = false;

    /**
     * Registers the AOP autoloader.
     *
     * @return void
     */
    public static function registerAutoloader()
    {
        if (self::$registered || !self::$initialized) {
            return;
        }
        Danslo_Aop_Model_Autoloader::init(Danslo_Aop_Aspect_Kernel::getInstance()->getContainer());
        self::$registered = true;
    }

    /**
     * Initializes the aspect kernel.
     *
     * @return void
     */
    public static function initializeAspectKernel()
    {
        if (self::$initialized) {
            return;
        }
        $useAopCache = Mage::app()->useCache(self::AOP_CACHE_TYPE);
        $aspectKernel = Danslo_Aop_Aspect_Kernel::getInstance();
        $aspectKernel->init(array(
            'debug' => Mage::getIsDeveloperMode() || !$useAopCache,
            'cacheDir' => self::_getCacheDir(),
            'excludePaths' => [
                // Some of the PHPUnit files don't like being autoloaded by go-aop. EconDev Autloader is initialized before
                dirname(Mage::getSingleton('ecomdev_composerautoload/setup')->getAutoloader()->getAutoloadFilePath() ?: Mage::getBaseDir()) . DS . 'phpunit',

                // While this may seem counterintuitive, we don't need go-aop to do
                // autoloading for us, as we register our own autoloader.
                Mage::getBaseDir() . DS . 'app',
                Mage::getBaseDir() . DS . 'lib'
            ]
        ));
        self::$initialized = true;
    }

    /**
     * Gets the AOP cache directory.
     *
     * @return string
     */
    protected static function _getCacheDir()
    {
        return Mage::getBaseDir('cache') . DS . self::AOP_CACHE_DIR;
    }

    /**
     * Clears the AOP cache.
     *
     * Our metadata will be cleared by the magento backend, we don't have to
     * do it here.
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public static function clearAopCache($observer)
    {
        $type = $observer->getType();
        if ($type && $type !== self::AOP_CACHE_TYPE) {
            return;
        }

        // Recursively clean up the cache directory.
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                self::_getCacheDir(),
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $path = $file->getRealPath();
            if ($file->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }
}