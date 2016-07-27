<?php

use Go\Core\AspectContainer;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Go\Instrument\Transformer\FilterInjectorTransformer;

class Danslo_Aop_Model_Autoloader
{
    /**
     * Instance of original autoloader
     *
     * @var EcomDev_ComposerAutoload_Model_Autoloader
     */
    protected $original;

    /**
     * Cache state
     *
     * @var array
     */
    private $cacheState;

    /**
     * Was initialization successful or not
     *
     * @var bool
     */
    private static $wasInitialized = false;

    protected $isCompilerEnabled;

    public function __construct(EcomDev_ComposerAutoload_Model_Autoloader $original, AspectContainer $container)
    {
        $this->original = $original;
        $this->cacheState = $container->get('aspect.cache.path.manager')->queryCacheState();
        $this->isCompilerEnabled = defined('COMPILER_INCLUDE_PATH');
    }

    /**
     * Initialize aspect autoloader
     *
     * Replaces original composer autoloader with wrapper
     *
     * @param AspectContainer $container
     *
     * @return bool was initialization sucessful or not
     */
    public static function init(AspectContainer $container)
    {
        Varien_Profiler::start(__METHOD__);
        $loaders = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;
            if (is_array($loader) && ($loader[0] instanceof EcomDev_ComposerAutoload_Model_Autoloader)) {
                $originalLoader = $loader[0];

                // Configure library loader for doctrine annotation loader
                AnnotationRegistry::registerLoader(function ($class) use ($originalLoader) {
                    $originalLoader->autoload($class);

                    return class_exists($class, false);
                });
                $loader[0] = new Danslo_Aop_Model_Autoloader($loader[0], $container);
                self::$wasInitialized = true;
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }
        Varien_Profiler::stop(__METHOD__);

        return self::$wasInitialized;
    }

    /**
     * Determines if this is a test class.
     *
     * @param string $className
     * @return boolean
     */
    protected function _isTestClass($className)
    {
        return strpos($className, 'Danslo_Aop_Test_') === 0;
    }

    /**
     * Attempt to load the given class.
     *
     * @param string $className
     * @return void
     */
    public function autoload($className)
    {
        Varien_Profiler::start(__METHOD__);
        // Don't do anything if we haven't initialized the aspect kernel yet.
        if (!Danslo_Aop_Model_Observer::$initialized) {
            return;
        }

        // Don't process classes that are part of the test suite.
        // The reason for this is that phpunit and doctrine (used by Go! AOP)
        // annotation checks interfere with eachother.
        // Let's assume we don't want to use AOP for the testcase classes ;)
        if ($this->_isTestClass($className)) {
            return;
        }

        $file = $this->findFile($className);

        if ($file) {
            include $file;
        }
        Varien_Profiler::stop(__METHOD__);
    }

    /**
     * Gets the class file from class name.
     *
     * @param string $className
     * @return string
     */
    protected function _getClassFile($className)
    {
        if ($this->isCompilerEnabled) {
            return COMPILER_INCLUDE_PATH . DIRECTORY_SEPARATOR . $className . '.php';
        }

        return str_replace(' ', DIRECTORY_SEPARATOR, ucwords(str_replace('_', ' ', $className))) . '.php';
    }

    /**
     * Resolve class name to file path
     *
     * @param $class
     * @return bool|string
     */
    public function findFile($class)
    {
        $file = false;
        $classFile = stream_resolve_include_path($this->_getClassFile($class));

        if (file_exists($classFile)) {
            $cacheState = isset($this->cacheState[$classFile]) ? $this->cacheState[$classFile] : null;
            if ($cacheState) {
                $file = $cacheState['cacheUri'] ?: $classFile;
            } else {
                $file = FilterInjectorTransformer::rewrite($classFile);
            }
        }

        return $file;
    }
}