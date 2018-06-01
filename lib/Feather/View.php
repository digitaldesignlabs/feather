<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * View
 * The View base class
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 * @todo Better documentation
 */
abstract class View
{
    public $template;
    protected $namespace;

    public function __construct()
    {
        $this->template = Template::instance();
        $this->loadView();
    }

    protected function loadView()
    {
        // Noop
    }

    protected function shouldRenderOutput()
    {
        return YES;
    }

    protected function willRenderOutput()
    {
        // Noop
    }

    public function render($namespace = "Main")
    {
        if ($this->shouldRenderOutput() === NO) {
            return "";
        }

        $this->willRenderOutput();
        return $this->template->render($namespace);
    }
}
