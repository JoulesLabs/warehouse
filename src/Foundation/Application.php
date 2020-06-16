<?php

namespace JoulesLabs\Warehouse\Foundation;

use JoulesLabs\Warehouse\Exception\ExceptionHandler;

class Application extends Container
{
    use PathsAndUrlsTrait, SetGetAttributesTrait, FacadeLoaderTrait, HelpersTrait;

    /**
     * Framework Version
     */
    const VERSION = '1.0.0';

    protected $prefix = 'v2';

    /**
     * $baseFile root plugin file path
     *
     * @var string
     */
    protected $baseFile = null;

    /**
     * The app config (/config/app.php)
     *
     * @var array
     */
    protected $appConfig = null;

    /**
     * Callbacks for framework's booted event
     *
     * @var array
     */
    protected $engineBootedCallbacks = [];

    /**
     * Callbacks for framework's ready event
     *
     * @var array
     */
    protected $pluginReadyCallbacks = [];

    /**
     * A flag to register the dynamic facade loader once
     *
     * @var boolean
     */
    protected $isFacadeLoaderRegistered = false;

    /**
     * Get application version
     *
     * @return string
     */
    public function version()
    {
        return self::VERSION;
    }

    /**
     * Init the application
     *
     * @param string $baseFile  (root plugin file path)
     * @param array  $appConfig (/config/app.php)
     */
    public function __construct($baseFile, $appConfig)
    {
        $this->baseFile = $baseFile;
        $this->appConfig = $appConfig;
        $this->bootstrapApplication();
    }

    /**
     * Bootup the application
     *
     * @param string $baseFile  (root plugin file path)
     * @param array  $appConfig (/config/app.php)
     */
    protected function bootstrapApplication()
    {
        $this->setAppBaseBindings();
        $this->setExceptionHandler();
        $this->loadApplicationTextDomain();
        $this->bootstrapWith($this->getEngineProviders());
        $this->fireCallbacks($this->engineBootedCallbacks);
        $this->bootstrapWith($this->getPluginProviders());
        $this->fireCallbacks($this->pluginReadyCallbacks);
    }

    /**
     * Register application base bindings
     */
    protected function setAppBaseBindings()
    {
        $this->bindAppInstance();
        $this->registerAppPaths();
        $this->registerAppUrls();
    }

    /**
     * Bind application instance
     */
    protected function bindAppInstance()
    {
        AppFacade::setApplication($this);
    }

    /**
     * Set Application paths
     */
    protected function registerAppPaths()
    {
        $path = plugin_dir_path($this->baseFile);
        $this->bindInstance('path', $path);
        $this->bindInstance('path.app', $path."/". $this->prefix."/".'app/');
        $this->bindInstance('path.config', $path."/". $this->prefix."/".'config/');
        $this->bindInstance('path.public', $path."/". $this->prefix."/".'public/');
        $this->bindInstance('path.framework', $path."/". $this->prefix."/".'framework/');
        $this->bindInstance('path.resource', $path."/". $this->prefix."/".'resources/');
        $this->bindInstance('path.storage', $path."/". $this->prefix."/".'storage/');
        $this->bindInstance('path.asset', $path."/". $this->prefix."/".'resources/assets/');
        $this->bindInstance('path.language', $path."/". $this->prefix."/".'resources/languages/');
        $this->bindInstance('path.view', $path."/". $this->prefix."/".'resources/views/');
        $this->bindInstance('path.vendor', $path. $this->prefix."/".'vendor/');
    }

    /**
     * Set Application urls
     */
    protected function registerAppUrls()
    {
        $url = plugin_dir_url($this->baseFile);
        $this->bindInstance('url', $url);
        $this->bindInstance('url.public', $url.'/'.$this->prefix.'/'.'public/');
        $this->bindInstance('url.resource', $url.'/'.$this->prefix.'/'.'resources/');
        $this->bindInstance('url.asset', $this->getAssetUrl($url.$this->prefix.'/'.'resources/assets/'));
        $this->bindInstance('url.vendor', $url.$this->prefix.'/vendor/');
    }

    /**
     * Set Application Exception Handler
     */
    protected function setExceptionHandler()
    {
        if (defined('WP_DEBUG') && WP_DEBUG && $this->getEnv() == 'dev') {
            return new ExceptionHandler($this);
        }
    }

    /**
     * load languages path for i18n pot files
     *
     * @return bool
     */
    protected function loadApplicationTextDomain()
    {
        return load_plugin_textdomain(
            $this->getTextDomain(),
            false,
            $this->languagePath()
        );
    }

    /**
     * Boot application with providers
     *
     * @param array $providers
     */
    public function bootstrapWith(array $providers)
    {
        $instances = [];

        foreach ($providers as $provider) {
            $instances[] = $instance = new $provider($this);
            $instance->booting();
        }

        if (!$this->isFacadeLoaderRegistered) {
            $this->registerAppFacadeLoader();
        }

        foreach ($instances as $object) {
            $object->booted();
        }
    }

    /**
     * Get engine/core providers
     *
     * @return array
     */
    public function getEngineProviders()
    {
        return $this->getProviders('core');
    }

    /**
     * Get plugin providers (Common)
     *
     * @return array
     */
    public function getCommonProviders()
    {
        return $this->getProviders('plugin')['common'];
    }

    /**
     * Get plugin providers (Backend|Frontend)
     *
     * @return array
     */
    public function getPluginProviders()
    {
        if ($this->isUserOnAdminArea()) {
            return $this->getProviders('plugin')['backend'];
        } else {
            return $this->getProviders('plugin')['frontend'];
        }
    }

    /**
     * Register booted events
     *
     * @param mixed $callback
     */
    public function booted($callback)
    {
        $this->engineBootedCallbacks[] = $this->parseHandler($callback);
    }

    /**
     * Register ready events
     *
     * @param mixed $callback
     */
    public function ready($callback)
    {
        $this->pluginReadyCallbacks[] = $this->parseHandler($callback);
    }

    /**
     * Fire application event's handlers
     *
     * @param array $callbacks
     */
    public function fireCallbacks(array $callbacks)
    {
        foreach ($callbacks as $callback) {
            call_user_func_array($callback, [$this]);
        }
    }

    private function getAssetUrl($path)
    {
        return $this->appConfig['asset_url'] ? : $path;
    }
}
