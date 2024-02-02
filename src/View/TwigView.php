<?php
declare(strict_types=1);

/**
 * This file is part of TwigView.
 *
 ** (c) 2014 Cees-Jan Kiewiet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WyriHaximus\TwigView\View;

use Cake\Core\Configure;
use Cake\View\View;
use Exception;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use WyriHaximus\TwigView\Event\ConstructEvent;
use WyriHaximus\TwigView\Event\EnvironmentConfigEvent;
use WyriHaximus\TwigView\Event\LoaderEvent;
use WyriHaximus\TwigView\Lib\Twig\Loader;

/**
 * Class TwigView.
 */
class TwigView extends View
{
    public const EXT = '.twig';

    public const ENV_CONFIG = 'WyriHaximus.TwigView.environment';

    /**
     * Extension to use.
     *
     * @var string
     */
    protected $_ext = self::EXT;

    /**
     * @var string[]
     */
    protected $extensions = [
        self::EXT,
        '.php',
    ];

    /**
     * Twig instance.
     *
     * @var \Twig\Environment
     */
    protected $twig;

    /**
     * Return empty string when View instance is cast to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return '';
    }

    /**
     * Initialize view.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->twig = new Environment($this->getLoader(), $this->resolveConfig());

        $this->getEventManager()->dispatch(ConstructEvent::create($this, $this->twig));

        $this->_ext = self::EXT;

        parent::initialize();
    }

    /**
     * @param string $extension Extension.
     * @return void
     */
    public function unshiftExtension(string $extension): void
    {
        array_unshift($this->extensions, $extension);
    }

    /**
     * Get twig environment instance.
     *
     * @return \Twig\Environment
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }

    /**
     * @param \Twig\Environment $twig Twig environment.
     * @return void
     * @internal
     */
    public function setTwig(Environment $twig): void
    {
        $this->twig = $twig;
    }

    /**
     * @return array
     */
    protected function resolveConfig(): array
    {
        $debug = Configure::read('debug', false);

        $config = $this->readConfig() + [
            'charset' => Configure::read('App.encoding', 'UTF-8'),
            'debug' => $debug,
            'cache' => $debug ? false : CACHE . 'twigView' . DS,
        ];

        if ($config['cache'] === true) {
            $config['cache'] = CACHE . 'twigView' . DS;
        }

        $configEvent = EnvironmentConfigEvent::create($config);
        $this->getEventManager()->dispatch($configEvent);

        return $configEvent->getConfig();
    }

    /**
     * @return array
     */
    protected function readConfig(): array
    {
        if (!Configure::check(static::ENV_CONFIG)) {
            return [];
        }

        $config = Configure::read(static::ENV_CONFIG);
        if (!is_array($config)) {
            return [];
        }

        return $config;
    }

    /**
     * Create the template loader.
     *
     * @return \Twig\Loader\LoaderInterface
     */
    protected function getLoader(): LoaderInterface
    {
        $event = LoaderEvent::create(new Loader());
        $this->getEventManager()->dispatch($event);

        return $event->getResultLoader();
    }

    /**
     * Render the template.
     *
     * @param string $viewFile Template file.
     * @param array $data Data that can be used by the template.
     * @throws \Exception
     * @return string
     */
    protected function _render(string $viewFile, array $data = []): string
    {
        if (empty($data)) {
            $data = $this->viewVars;
        }

        if (substr($viewFile, -3) === 'php') {
            $out = parent::_render($viewFile, $data);
        } else {
            $data = array_merge(
                $data,
                iterator_to_array($this->helpers()->getIterator()),
                [
                    '_view' => $this,
                ]
            );

            try {
                $out = $this->getTwig()->load($viewFile)->render($data);
            } catch (Exception $e) {
                $previous = $e->getPrevious();

                if ($previous !== null && $previous instanceof Exception) {
                    throw $previous;
                } else {
                    throw $e;
                }
            }
        }

        return $out;
    }

    /**
     * @param string|null $name Template name.
     * @throws \Exception
     * @return string
     */
    protected function _getTemplateFileName(?string $name = null): string
    {
        $rethrow = new Exception('You\'re not supposed to get here');
        foreach ($this->extensions as $extension) {
            $this->_ext = $extension;
            try {
                return parent::_getTemplateFileName($name);
            } catch (Exception $exception) {
                $rethrow = $exception;
            }
        }

        throw $rethrow;
    }

    /**
     * @param string|null $name Layout name.
     * @throws \Exception
     * @return string
     */
    protected function _getLayoutFileName(?string $name = null): string
    {
        $rethrow = new Exception('You\'re not supposed to get here');
        foreach ($this->extensions as $extension) {
            $this->_ext = $extension;
            try {
                return parent::_getLayoutFileName($name);
            } catch (Exception $exception) {
                $rethrow = $exception;
            }
        }

        throw $rethrow;
    }

    /**
     * @param string $name Element name.
     * @param bool $pluginCheck Whether to check within plugin.
     * @return string|false
     */
    protected function _getElementFileName(string $name, bool $pluginCheck = true)
    {
        foreach ($this->extensions as $extension) {
            $this->_ext = $extension;
            $result = parent::_getElementFileName($name, $pluginCheck);
            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }
}
