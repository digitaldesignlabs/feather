<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * View
 * The View base class
 *
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
abstract class View
{
    /**
     * The template singleton
     * @access public
     * @var Feather\Template
     */
    public $template;

    /**
     * The namespace of this view
     * I'm not sure this works how I think it works...
     * @access protected
     * @var string
     */
    protected $namespace;

    /**
     * __construct()
     * Initialize the view object
     *
     * @access public
     */
    public function __construct()
    {
        $this->template = Template::instance();
        $this->loadView();
    }

    /**
     * loadView()
     * Set up this view. Should be overloaded by subclasses to actually
     * set up the specific view.
     *
     * @access protected
     */
    protected function loadView()
    {
        // Noop
    }

    /**
     * shouldRenderOutput()
     * If this returns false, then render() will simply return an empty string
     *
     * @access protected
     * @return boolean
     */
    protected function shouldRenderOutput()
    {
        return YES;
    }

    /**
     * willRenderOutput()
     * Callback executed just before the content is rendered. Can be used to
     * hook-in for last-minute cleanup and changes
     *
     * @access protected
     */
    protected function willRenderOutput()
    {
        // Noop
    }

    /**
     * render()
     * Render the specified mainspace.
     *
     * @access public
     * @param string $namespace (default Main)
     * @return string
     */
    public function render($namespace = "Main")
    {
        if ($this->shouldRenderOutput() === NO) {
            return "";
        }

        $this->willRenderOutput();
        return $this->template->render($namespace);
    }
}
