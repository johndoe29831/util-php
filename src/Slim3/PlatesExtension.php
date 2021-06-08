<?php
namespace Jd29\Slim3;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Psr\Http\Message\UriInterface;
use Slim\Interfaces\RouterInterface;


class PlatesExtension implements ExtensionInterface
{
    /**
     * @var \Slim\Interfaces\RouterInterface
     */
    private $router;

    /**
     * @var \Slim\Http\Uri
     */
    private $uri;

    /**
     * Create new Asset instance.
     *
     * @param \Slim\Interfaces\RouterInterface $router
     * @param \Psr\Http\Message\UriInterface   $uri
     */
    public function __construct(RouterInterface $router, UriInterface $uri)
    {
        $this->router = $router;
        $this->uri = $uri;
    }

    /**
     * Register extension function.
     *
     * @param \League\Plates\Engine $engine Plates instance
     */
    public function register(Engine $engine)
    {
        $engine->registerFunction('baseUrl', [$this, 'baseUrl']);
        $engine->registerFunction('uriFull', [$this, 'uriFull']);
        $engine->registerFunction('pathFor', [$this->router, 'pathFor']);
        $engine->registerFunction('basePath', [$this->uri, 'getBasePath']);
        $engine->registerFunction('uriScheme', [$this->uri, 'getScheme']);
        $engine->registerFunction('uriHost', [$this->uri, 'getHost']);
        $engine->registerFunction('uriPort', [$this->uri, 'getPort']);
        $engine->registerFunction('uriPath', [$this->uri, 'getPath']);
        $engine->registerFunction('uriQuery', [$this->uri, 'getQuery']);
        $engine->registerFunction('uriFragment', [$this->uri, 'getFragment']);
		
        $engine->registerFunction('basePathSite', [$this, 'basePathSite']);
    }

    /**
     * Retrieve slim baseUrl.
     *
     * @param string $permalink You can add optional permalink
     *
     * @return string
     */
    public function baseUrl($permalink = '')
    {
        return $this->uri->getBaseUrl().'/'.ltrim($permalink, '/');
    }

    /**
     * Retrieve slim uri (string).
     *
     * @return string
     */
    public function uriFull()
    {
        return (string) $this->uri;
    }
	
    public function basePathSite()
    {
        #return $this->router->pathFor();
		$base_path = $this->uri->getBasePath();
		$path_info = pathinfo($base_path);
		return isset($path_info['extension']) ? $path_info['dirname'] : $base_path;
    }
	
}
