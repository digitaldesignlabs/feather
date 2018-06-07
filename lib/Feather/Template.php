<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

use \RecursiveIteratorIterator;
use \RecursiveArrayIterator;

/**
 * Template
 * A logic-free page templating engine
 *
 * @author Mike Hall
 * @author Richard Mann
 * @author James Dempster
 * @copyright GG.COM Ltd 2004-2018
 * @copyright Digital Design Labs Ltd 2018
 * @license MIT
 */
class Template extends Singleton
{
    /**
     * Will remove any HTML comments found in the template files, unless it uses a special style
     * i.e. the open comment block is immediately followed by an @ symbol
     * <!--@
     *   Comment written here
     * -->
     *
     * @var bool
     * @access public
     */
    public $removeHTMLComments = YES;

    /**
     * If any {{unsused}} {{{names}}} are found they will be removed
     *
     * @var bool
     * @access public
     */
    public $removeUnusedNames = YES;

    /**
     * Root block container
     *
     * @var array
     * @access private
     */
    private $rootBlock = null;

    /**
     * Container for the masks registered on this object
     *
     * @var array
     * @access private
     */
    private $maskList = array();

    /**
     * Container for post-parse processors
     *
     * @var array
     * @access private
     */
    private $processorList = array();

    /**
     * __construct()
     *
     * @ignore
     */
    public function __construct()
    {
        $this->rootBlock = $this->emptyBlock();
    }

    /**
     * emptyBlock()
     *
     * @access private
     * @return array
     */
    private function emptyBlock()
    {
        return array(
            "code" => "",
            "children" => array(),
            "variables" => array(),
        );
    }

    /**
     * encode()
     * Encode quotes within the templates
     *
     * @access private
     * @param string $code
     * @return string - Encoded code to protect characters through the templater
     */
    private function encode(string $code)
    {
        return str_replace(['"', "'"], ['__DQUOT__', '__QUOT__'], $code);
    }

    /**
     * decode()
     * Decode previously encoded quotes
     *
     * @access private
     * @param string $code
     * @return string - Inverse of encode
     */
    private function decode(string $code)
    {
        return str_replace(['__QUOT__', '__DQUOT__'], ["'", '"'], $code);
    }

    /**
     * loadTemplate()
     * Loads a template file, or block within it, from the file system into the address specified
     *
     * <b>Example - Load whole file</b>
     * <code>
     * $template = Template::instance();
     * $template->loadTemplate("page_test.html", "Main");
     * </code>
     *
     * <b>Example - Loading just a single block from within the file</b>
     * <code>
     * $template = Template::instance();
     * $template->loadTemplate("page_test2.html", "Main", "Table1");
     * </code>
     *
     * To specify a child of a child, use a colon to move down the tree e.g Table1:Row
     * would be a block named Row that is nested inside a block named Table
     *
     * @access public
     * @param string $templateFilename - The filename of the template to load
     * @param string $address - The address to load the block into
     * @param string $childBlockAddress (optional) - The name of a specific block from the template to load.
     *     If not specified, all blocks will be loaded
     * @return string The address of the new block
     */
    public function loadTemplate(string $templateFilename, $address, $childBlockAddress = null)
    {
        if (is_readable($templateFilename) === NO) {
            throw new Error("File not found {$templateFilename}");
        }

        $content = file_get_contents($templateFilename);
        return $this->loadTemplateFromString($content, $address, $childBlockAddress);
    }

    /**
     * loadTemplateFromString()
     * Loads a template based upon a string, rather than from a file. In fact, the file function just calls
     * this function internally.
     *
     * <b>Example</b>
     * <code>
     * $template = Template::instance();
     * $template->loadTemplateFromString("<ul><!--START:Record--><li>{{ListItem}}</li><!--END:Record--></ul>", "Main");
     * </code>
     *
     * @access public
     * @param string $templateSource - String to use as template
     * @param string $address - The address to load the block into
     * @param string $childBlockAddress (optional) - The name of a specific block from the template to load.
     *     If not specified, all blocks will be loaded
     * @return string The address of the new block
     */
    public function loadTemplateFromString($templateSource, $address, $childBlockAddress = null)
    {
        // Encode quotes and other literals
        $templateSource = $this->encode($templateSource);

        // Create a new block from this source code, and then narrow down to just
        // the requested child block, if an address is supplied
        $newBlock = $this->generateNewBlock($templateSource);
        if (empty($childBlockAddress) === NO) {
            $newBlock = $this->seek($childBlockAddress, $newBlock);
        }

        // Now we have the new block, we need to figure out where it goes from the address
        $path = explode(":", $address);
        $blockName = array_pop($path);

        // Find the target block we are going to write too, creating child blocks as required
        $target =& $this->seek($path, $this->rootBlock, ["create" => YES]);
        $target["children"][$blockName] = $newBlock;
        return $address;
    }

    /**
     * generateNewBlock()
     * Generate a new block from a supplied string
     *
     * @access private
     * @param string $templateSource
     * @return void
     */
    private function generateNewBlock($templateSource)
    {
        $block = $this->emptyBlock();

        $block["code"] = preg_replace_callback(
            '/<!--START:([a-zA-Z]\w+)-->\n?(.*?)\s*<!--END:\1-->\n?/s',
            function ($matches) use (&$block) {
                return $this->generateChildBlocks($matches[1], $matches[2], $block);
            },
            $templateSource
        );

        return $block;
    }

    /**
     * generateChildBlocks()
     * Generate child blocks for a block we are creating
     *
     * @access private
     * @param string $blockName
     * @param string $content
     * @param array $parentBlock (by reference)
     * @return string Placeholder for the new child
     */
    private function generateChildBlocks($blockName, $content, &$parentBlock)
    {
        $parentBlock["children"][$blockName] = $this->generateNewBlock($content);
        return sprintf("{{{%s}}}", $blockName);
    }

    /**
     * seek()
     * Locate a block from it's address and return a reference
     *
     * @param string $address
     * @param array $block (by reference)
     * @param array $options
     * @param boolean $options["create"] Should the block be created if not found?
     * @return array Reference to the requsted block
     */
    private function &seek($address, &$block, array $options = [])
    {
        if (is_string($address) === YES) {
            $address = explode(":", $address);
        }

        // An empty address means we are talking about us
        if (empty($address) === YES) {
            return $block;
        }

        // Find the child block which contains the target block
        $nexthop = array_shift($address);

        // If that child block doesn't exist, and we have been asked to create it, then do so
        if (isset($block["children"][$nexthop]) === NO && empty($options["create"]) === YES) {
            $block["children"][$nexthop] = $this->emptyBlock();
        }

        // If that child block still doesn't exist, then error
        if (isset($block["children"][$nexthop]) === NO) {
            throw new Error("Invalid namespace: {$address}");
        }

        // Otherwise, recursively search for this block
        return $this->seek($address, $block["children"][$nexthop]);
    }

    /**
     * assign()
     * Assigns a variable to b specified block. If no block address is found, apply the variable globally.
     *
     * <b>Example - Name Replacement</b>
     * <code>
     * $template = Template::instance();
     * $template->loadTemplate("page_test.html", "Main");
     * $template->assign(array("Name" => "Richard", "Age" => 20), "Main:Content:UserDetails");
     * echo $template-render("Main");
     * </code>
     *
     * <b>Example - Multi Row</b>
     * <code>
     * $template = Template::instance();
     * $template->loadTemplate("page_test.html", "Main");
     * $template->assign(array(
     *     array("Name" => "Richard", "Age" => 20 ),
     *     array("Name" => "Tom", "Age" => 18 ),
     *     array("Name" => "Luke", "Age" => 35 ),
     *     array("Name" => "James", "Age" => 23 )
     * ), "Main:Content:UserDetails");
     * echo $template->render("Main");
     * </code>
     *
     * @param array $data
     * @param string $address (optional)
     */
    public function assign(array $data, $address = [])
    {
        if (is_array($data) === NO) {
            throw new Error("Supplied data must be an array");
        }

        // Rationalize the address to an array
        if (is_string($address) === YES) {
            $address = explode(":", $address);
        }

        // Find the block to assign this  data to
        $target =& $this->seek($address, $this->rootBlock);

        // Find the right section of the block and apply the data to it
        foreach ($data as $name => $value) {
            if (is_string($name) === YES) {
                if (is_array($value) && is_callable($value) === NO) {
                    $value = $this->smoosh($value);
                    foreach ($value as $subname => $subvalue) {
                        $target["variables"]["$name.$subname"] = $subvalue;
                    }
                } else {
                    $target["variables"][$name] = $value;
                }
            } else {
                $target["variables"][] = $this->smoosh($value);
            }
        }
    }

    /**
     * smoosh()
     * Flatten a multi-dimensional array down to dot-notation. e.g.
     * ["foo" => ["bar" => "baz"]] becomes ["foo.bar" => "baz"]
     *
     * @param array $values
     * @return @array
     */
    private function smoosh(array $values)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($values),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $path = [];
        $smooshed = [];

        foreach ($iterator as $key => $value) {
            $path[$iterator->getDepth()] = $key;
            if (is_array($value) === NO) {
                $fullpath = implode(".", array_slice($path, 0, $iterator->getDepth() + 1));
                $smooshed[$fullpath] = $value;
            }
        }
        return $smooshed;
    }

    /**
     * addMask()
     * Allows adding of masks for variable names. Varibles e.g {{Test}} can add a mask name e.g {{Test|YesNo}}
     * When rendering the template, a function defined by the mask YesNo will be applied to the value of Test
     * before it us substituted into the page.  You may also chain masks, e.g. {{Test|YesNo|toUpperCase}}
     * If no value is set for Test, then the mask will NOT BE CALLED.
     *
     * <b>Example - Basic function</b>
     * <code>
     *
     * $template = Template::instance();
     * $template->addMask("YesNo", function ($value) {
     *     return $value ? "Yes" : "No";
     * });
     * $template->loadTemplate("page_test.html", "Main");
     * $template->assign(array("YesOrNo" => 1), "Main");
     * echo $template->render("Main");
     * </code>
     *
     * In the above example YesOrNo will be parsed with Yes.
     *
     * @param string $maskName - name of the mask
     * @param callable $callback - valid callback {@link http://php.net/callback}
     * @return
     */
    public function addMask($maskName, $callback)
    {
        // Validating the input
        if (is_callable($callback) === NO) {
            throw new Error("Callback for mask named {$maskName} is not callable");
        }

        // Add to the mask list
        $this->maskList[$maskName] = array(
            "callback" => $callback,
            "params" => array_slice(func_get_args(), 2),
        );
    }

    /**
     * addProcessor()
     * Adds a post-processor function to the template. Post-processors are run on a rendered content,
     * immediately before it is returned to the script.
     *
     * <b>Example - Basic function</b>
     * <code>
     * function enFrancais($value) {
     *    return str_replace("Yes", "Oui", $value);
     * }
     *
     * $template = Template::instance();
     * $template->loadTemplateFromString("Yes, okay", "Main");
     * $template->addProcessor("enFrancais");
     * echo $template->render("Main"); // Oui, okay
     * </code>
     *
     * @param callable $callback
     */
    public function addProcessor($callback)
    {
        if (is_callable($callback) === NO) {
            throw new Error("Requested post-processor is not a valid callback");
        }
        $this->processorList[] = $callback;
    }

    /**
     * removeBlock()
     * Removes the block with the specified address
     *
     * @access public
     * @param string $nameSpace
     */
    public function removeBlock($address)
    {
        if (is_string($address) === YES) {
            $address = explode(":", $address);
        }

        $blockName = array_pop($address);
        $parentBlock =& $this->seek($address, $this->rootBlock);
        unset($parentBlock["children"][$blockName]);
    }

    /**
     * removeVariable()
     * Removes a variable of the name provided, from the block at this address. If no variable
     * name is supplied, then it will remove all variables from the block at the address.
     *
     * @access public
     * @param string $address The address of the block
     * @param string $variableName (optional) - The name of the variable to remove
     */
    public function removeVariable($address, $variableName = null)
    {
        // Locate the target block
        $target =& $this->seek($address, $this->rootBlock);

        // No second argument means we should remove all variables.
        // Otherwise, just remove the specified one
        if (empty($variableName) === YES) {
            $target["variables"] = array();
        } else {
            unset($target["variables"][$variableName]);
        }
    }

    /**
     * render()
     * Render the block at the specified address
     *
     * <b>Example</b>
     * <code>
     * $template = Template::instance();
     * $template->loadTemplate("page_test.html", "Main");
     * $template->assign(array("PageName" => "Test Page"), "Main");
     * echo $template->render("Main");
     * </code>
     *
     * @param string $address
     * @return string
     */
    public function render($address)
    {
        // Find the block we want and process it
        $block = $this->seek($address, $this->rootBlock);
        $value = $this->parseBlock($block);

        // Replace any string literals with temp values
        $value = preg_replace_callback(
            '/{{`([^`]+)`(?:\|([\w\|]+))?}}/',
            function ($matches) {
                $tempname = hash("sha256", $matches[1]);
                $this->assign([$tempname => $matches[1]]);
                if (empty($matches[2])) {
                    return "{{" . $tempname. "}}";
                }
                return sprintf("{{%s|%s}}", $tempname, $matches[2]);
            },
            $value
        );

        // Substitute variables from global scope
        if (empty($this->rootBlock["variables"]) === NO) {
            $value = $this->nameReplace($this->rootBlock["variables"], $value);
        }

        // Strip out unused elements
        if ($this->removeUnusedNames === YES) {
            $value = preg_replace('/{{{?[\w\.]+(?:\|[\w\|]+)?}?}}/', "", $value);
        }

        // Strip out unused comments
        if ($this->removeHTMLComments === YES) {
            $value = preg_replace('/<!--[^@].*?-->/s', "", $value);
        }

        // Decode encoded stuff
        $value = $this->decode($value);

        // Execute all defined post-processors
        return Collection::reduce($this->processorList, function ($carry, $func) {
            return call_user_func($func, $carry);
        }, $value);
    }

    /**
     * parseBlock()
     * Parse the code in the specified block, adding in iterations and local variables where appropriate
     *
     * @access private
     * @param mixed $block
     * @return string - Rendered value of that block
     */
    private function parseBlock(&$block)
    {
        // If there are no variables, then the code is the value
        if (empty($block["variables"]) === YES) {
            $value = $block["code"];

        // If the this block has a record set rather than records, then iterate
        } elseif (is_array(current($block["variables"])) === YES) {

            $rows = Collection::map($block["variables"], function ($record, $index) use ($block) {
                $record["templateRowNum"] = 1 + $index;
                $record["templateRowFirst"] = $index === 0;
                $record["templateRowLast"] = count($block["variables"]) === $index + 1;
                $record["templateRowOdd"] = $index % 2 === 1;
                $record["templateRowEven"] = $index % 2 === 0;
                return $this->nameReplace($record, $block["code"]);
            });

            $value = implode("", $rows);

        // Otherwise, do a straight replace
        } else {

            $value = $this->nameReplace($block["variables"], $block["code"]);
        }

        // If there are children, also process those
        if (empty($block["children"]) === NO) {

            $find = array();
            $replace = array();

            // Loop through the child blocks, parsing them and substituting into the code
            foreach ($block["children"] as $childName => $childBlock) {
                $childValue = $this->parseBlock($childBlock);
                $find[] = "{{{" . $childName . "}}}";
                $replace[] = $childValue;
            }

            // Perform the switch
            $value = str_replace($find, $replace, $value);
        }

        // Return the parsed code
        return $value;
    }

    /**
     * maskReplace()
     * Given a mask name, will check to see if a mask exists if so
     * calls the mask with the value and returns mask value.
     *
     * @access private
     * @param string $maskName
     * @param string $value
     * @return string
     */
    private function maskReplace($maskName, $value)
    {
        // Otherwise, check if this mask exists?
        $maskExists = isset($this->maskList[$maskName]) && is_callable($this->maskList[$maskName]["callback"]);
        if ($maskExists === NO) {
            return $value;
        }

        // Yep - get the parameters for this mask
        $params = $this->maskList[$maskName]["params"];
        if (is_array($params) === NO) {
            $params = array($params);
        }

        // Put the value to mask at the start of the callback parameters then
        // execute the callback and return the result
        array_unshift($params, $value);
        return call_user_func_array($this->maskList[$maskName]["callback"], $params);
    }

    /**
     * nameReplace()
     * Searches for names that need replacing, including names with masks applied to them
     *
     * @access private
     * @param array $variables
     * @param string $code
     * @return string
     */
    private function nameReplace($variables, $code)
    {
        if (empty($variables) === YES) {
            return $code;
        }

        foreach ($variables as $name => $value) {

            // If this value is a callback, execute it
            if (is_scalar($value) === NO && is_callable($value) === YES) {
                $value = call_use_func($value);
            }

            // If the value is a View object, render it
            if ($value instanceof View) {
                $value = $value->render();
            }

            // Values enclosed in triple curly braces can be replaced now.
            // We also need to look for masks, which are seperated by pipes
            $code = preg_replace_callback(
                '/{{{' . $name . '(?:\|([\w\|]+))?}}}/',
                function ($matches) use ($value) {

                    // If there are no masks then use the value as-is
                    if (empty($matches[1]) === YES) {
                        return $value;
                    }

                    // Apply the masks in order
                    return Collection::reduce(
                        explode("|", $matches[1]),
                        function ($carry, $mask) {
                            return $this->maskReplace($mask, $carry);
                        },
                        $value
                    );
                },
                $code
            );

            // Values enclosed in double curly braces can be replaced now.
            // Double-braces are escaped before rendering
            $code = preg_replace_callback(
                '/{{' . $name . '(?:\|([\w\|]+))?}}/',
                function ($matches) use ($value) {

                    // Escape the value before we start
                    $value = htmlspecialchars($value);

                    // If there are no masks then use the value as-is
                    if (empty($matches[1]) === YES) {
                        return $value;
                    }

                    // Apply the masks in order
                    return Collection::reduce(
                        explode("|", $matches[1]),
                        function ($carry, $mask) {
                            return $this->maskReplace($mask, $carry);
                        },
                        $value
                    );
                },
                $code
            );
        }

        // Return the processed code
        return $code;
    }
}
