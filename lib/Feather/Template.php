<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Template
 * A logic-free page templater
 * @author Richard Mann
 * @author James Dempster
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 * @todo Rework the error handling, it's shocking.
 */
class Template extends Singleton
{
    /**
     * Will remove any special HTML styled comments found in the templte files
     * the special style basicly has an @ after the open comment block e.g.
     * <!--@
     *   Comment written here
     * -->
     *
     * @var bool
     * @access public
     */
    public $removeHTMLComments = YES;

    /**
     * If any unsused names are found {example} will be removed
     *
     * @var bool
     * @access public
     */
    public $removeUnusedNames = YES;

    /**
     * Location of where the cache is to be stored, static var affects all template objects
     *
     * <b>Example</b>
     * <code>
     * Escher\Template::$cacheLocation = "/tmp/template_cache/";
     * $tmpl = new Template();
     * </code>
     *
     * @var string
     * @access public
     */
    public static $cacheLocation;

    /**
     * Code Block Containers
     * @var array
     * @access private
     */
    private $codeBlocks = array();
    private $codeBlocksCache = array();

    /**
     * Container for the masks registered on this object
     * @var array
     * @access private
     */
    private static $maskList = array();

    /**
     * Container for post-parse processors
     * @var array
     * @access private
     */
    private $processorList = array();

    /**
     * Error constants
     * @const int
     * @access public
     */
    const ERROR_INVALID_NAMESPACE = 1;
    const ERROR_FILE_NOT_FOUND = 2;
    const ERROR_NOT_VALID = 3;

    /**
     * Sets the file extension for template files
     * @const string
     * @access public
     */
    const CACHE_EXTENSION = ".template";

    /**
     * Escher\Template::instance()
     * @ignore
     */
    public static function &instance($new = NO)
    {
        // Get a singleton
        $template = parent::instance($new);

        // Add in the global vars
        if (empty($GLOBALS["templateGlob"]) === NO) {
            $template->assign($GLOBALS["templateGlob"]);
        }

        return $template;
    }

    /**
     * Escher\Template::__construct()
     * @ignore
     */
    public function __construct()
    {
        // Set default cache location
        if (empty(self::$cacheLocation)) {
            self::$cacheLocation = sys_get_temp_dir();
        }

        // Initialise the code block container
        $this->codeBlocks = $this->emptyCodeBlock();
        $this->codeBlocksCache = $this->emptyCodeBlock();

        // Set up some common masks
        self::addMask("YesNo", array($this, "maskYesNo"));
        self::addMask("CheckboxSelected", array($this, "maskCheckboxSelected"));
        self::addMask("htmlspecialchars", "htmlspecialchars");
    }

    /**
     * Escher\Template::addProcessor()
     *
     * Adds a post-processor function to the template.
     *
     * Post-processors are run on a parsed string, immediately
     * before it is returned to the script.
     *
     * <b>Example - Basic function</b>
     * <code>
     * function enFrancais($value) {
     *    return str_replace("Yes", "Oui", $value);
     * }
     *
     * $template = Escher\Template::instance();
     * Escher\Template::addProcessor("enFrancais");
     * $template->loadTemplate("page_test.html", "Main");
     * echo $template->render("Main"); // all "Yes" string will be "Oui"
     * </code>
     *
     * @param callable $callback - valid callback {@link http://php.net/callback}
     * @return
     */
    public function addProcessor($callback)
    {
        if (is_callable($callback)) {
            $this->processorList[] = $callback;
        } else {
            $this->fault(self::ERROR_NOT_VALID, "Processor Callback Invalid");
        }
    }

    /**
     * Escher\Template::addMask()
     *
     * Allows adding of masks for variable names
     *
     * Named varibles e.g {Test} can add a mask name e.g {Test[YesNo]}
     * which when replacing the name Test with a value, will first run the value
     * though the mask callback to allow any modifcation to the value.
     *
     * <b>Example - Basic function</b>
     * <code>
     * function fnYesNo($value) {
     *     return $value ? "Yes" : "No";
     * }
     * Escher\Template::addMask("YesNo", "fnYesNo");
     * $template = new Template();
     * $template->loadTemplate("page_test.html", "Main");
     * $template->assign(array("YesOrNo" => 1), "Main");
     * echo $template->render("Main");
     * </code>
     *
     * In the above example YesOrNo will be parsed with Yes
     *
     * @param string $maskName - name of the mask
     * @param callable $callback - valid callback {@link http://php.net/callback}
     * @return
     */
    public static function addMask($maskName, $callback, $param = array())
    {
        if (is_callable($callback)) {
            self::$maskList[$maskName]["callback"] = $callback;
            self::$maskList[$maskName]["param"] = $param;
        } else {
            self::fault(self::ERROR_NOT_VALID, "Callback Invalid");
        }
    }

    /**
     * Escher\Template::maskReplace()
     *
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
        $maskExists = isset(self::$maskList[$maskName]) && is_callable(self::$maskList[$maskName]["callback"]);
        if ($maskExists) {

            // Yep - get the parameters for this mask
            $param = self::$maskList[$maskName]["param"];
            if (is_array($param) === NO) {
                $param = array($param);
            }

            // Put the value to mask at the start of
            // the callback parameters
            array_unshift($param, $value);

            // Execute the callback and return the result
            return call_user_func_array(self::$maskList[$maskName]["callback"], $param);
        }

        // If we get here, the callback didn"t exist
        // or wasn"t valid. Just return as is
        return $value;
    }

    /**
     * Escher\Template::maskYesNo()
     *
     * Evaluates $value to Yes or No
     *
     * @access private
     * @param bool $value
     * @return string - Yes or No
     */
    private function maskYesNo($value)
    {
        return $value ? "Yes" : "No";
    }

    /**
     * Escher\Template::maskCheckboxSelected()
     *
     * Evaluates $value to "checked="checked"' or ""
     *
     * @access private
     * @param bool $value
     * @return string - 'checked="checked"' or ""
     */
    private function maskCheckboxSelected($value)
    {
        if ($value === "no") {
            return "";
        }

        return $value ? ' checked="checked"' : "";
    }

    /**
     * Escher\Template::maskOptionSelected()
     *
     * Evaluates $value to 'selected="selected"' or ""
     *
     * @access private
     * @param bool $value
     * @return string - 'selected="selected"' or ""
     */
    private function maskOptionSelected($value, $selected)
    {
        return ($value === $selected) ? ' selected="selected"' : "";
    }

    /**
     * Escher\Template::maskDateFormat()
     *
     * Formats a passed date to the passed format
     *
     * @access private
     * @param string $date
     * @param string $format
     * @return string - formatted date, or original string on error
     */
    private function maskDateFormat($date, $format)
    {
        if ($timestamp = strtotime($date)) {
            return date($format, $timestamp);
        }

        return $date;
    }

    /**
     * Escher\Template::loadTemplate()
     *
     * Loads a template file, or block within, from the file system into the template object for use
     *
     * <b>Example - Load whole file</b>
     * <code>
     * $template = new Template();
     * $template->loadTemplate("page_test.html", "Main");
     * </code>
     *
     * <b>Example - Code Block</b>
     * <code>
     * $template = new Template();
     * $template->loadTemplate("page_test2.html", "Main", "Table1");
     * </code>
     *
     * To specify subcodeblock use a colon to move down the tree e.g Table1:Row
     * would be a codeblock named Row that is nested inside a codeblock named Table
     *
     * @access public
     * @param string $templateFilename - The filename of the template to load
     * @param string $namespace - The namespace to load the template into.
     * @param string $codeBlock (optional) - The name of a specific code block in the template to load.
     *     If not specified, all blocks will be loaded
     * @return bool - NO on error
     */
    public function loadTemplate($templateFilename, $namespace, $codeBlock = "")
    {
        // Check the template file specified is valid
        if (is_readable($templateFilename)) {

            // Get the proper path to the template. We'll need
            // this later when we're caching it. We don't want
            // to cache the same files twice because they were
            // accessed with different paths
            $foundTemplateFile = realpath($templateFilename);

        } else {

            // Trigger a fault as the template file could not be found
            $this->fault(self::ERROR_FILE_NOT_FOUND, $templateFilename);
            return NO;
        }

        // Lets get this template into our cache if it isn't in there
        // already. Saves re-parsing it on each request
        if ($this->cacheValid($foundTemplateFile) === NO) {

            // Load the template data
            $templateSource = file_get_contents($foundTemplateFile);
            $this->encode($templateSource);

            // Cache this data so we don't have to re-parse this template again
            // Maybe move this cache into Memcache and speed it up?
            $this->buildCodeBlocks($templateSource, $this->codeBlocksCache);
            $this->cacheWrite($foundTemplateFile, $this->codeBlocksCache);
        }

        // Read the data from the cache
        $this->codeBlocksCache = array();
        $this->codeBlocksCache = $this->cacheRead($foundTemplateFile);

        // When there is a specified sub-code block,
        // then retrieve that block only from the data
        // we have already parsed
        if ($codeBlock !== "") {
            $this->codeBlocksCache = &$this->getNamespace($codeBlock, $this->codeBlocksCache);
        }

        // Create a new block with the specified name
        $codeBlockPointer = &$this->createNamespace($namespace);

        // Fill it with the parsed code blocks
        $codeBlockPointer = $this->codeBlocksCache;
        return $namespace;
    }

    /**
     * Escher\Template::loadTemplateFromString()
     *
     * Loads a template based upon a passed string, rather than from a file
     *
     * <b>Example</b>
     * <code>
     * $template = Escher\Template::instance();
     * $template->loadTemplateFromString("<ul><!--START:Record--><li>{{ListItem}}</li><!--END:Record--></ul>", "Main");
     * </code>
     *
     * To specify subcodeblock use a colon to move down the tree e.g Table1:Row
     * would be a codeblock named Row that is nested inside a codeblock named Table
     *
     * @access public
     * @param string $templateSource - String to use as template
     * @param string $namespace - The namespace to load the template into.
     * @param string $codeBlock (optional) - The name of a specific code block in the template to load.
     *     If not specified, all block will be loaded
     * @return bool - NO on error
     */
    public function loadTemplateFromString($templateSource, $namespace, $codeBlock = "")
    {
        // Check if there is a cached version of this string
        $cacheFilename = self::$cacheLocation . "/" . sha1($templateSource) . self::CACHE_EXTENSION;
        if (file_exists($cacheFilename)) {

            // Load the data from the cache
            $this->codeBlocksCache = unserialize(file_get_contents($cacheFilename));

        } else {

            // Generate code blocks
            $this->encode($templateSource);

            // Construct the code blocks
            $this->codeBlocksCache = array();
            $this->buildCodeBlocks($templateSource, $this->codeBlocksCache);

            // Write to disc
            file_put_contents($cacheFilename, serialize($this->codeBlocksCache));
        }

        // When there is a specified sub-code block,
        // then retrieve that block only from the data
        // we have already parsed
        if ($codeBlock !== "") {
            $this->codeBlocksCache = &$this->getNamespace($codeBlock, $this->codeBlocksCache);
        }

        // Create a new block with the specified name
        $codeBlockPointer = &$this->createNamespace($namespace);

        // Fill it with the parsed code blocks
        $codeBlockPointer = $this->codeBlocksCache;

        return YES;
    }

    /**
     * Escher\Template::buildCodeBlocks()
     *
     * @access private
     * @param string $templateSource
     * @param array $codeBlock
     * @return void
     */
    private function buildCodeBlocks($templateSource, &$codeBlock)
    {
        $codeBlock["code"] = preg_replace_callback(
            '/\s*<!--START:([A-z]\w+)-->\n?(.*?)\s*<!--END:\1-->\n?/s',
            function ($matches) use (&$codeBlock) {
                return $this->buildChildCodeBlocks($matches[1], $matches[2], $codeBlock);
            },
            $templateSource
        );
    }

    /**
     * Escher\Template::buildChildCodeBlocks()
     *
     * @access private
     * @param string $blockName
     * @param string $content
     * @param array $codeBlock byRef
     * @return string - Value to replace the found block with
     */
    private function buildChildCodeBlocks($blockName, $content, &$codeBlock)
    {
        // Create the code block for this child to use
        $codeBlock["children"][$blockName] = $this->emptyCodeBlock();
        $this->buildCodeBlocks($content, $codeBlock["children"][$blockName]);

        // Replace the code block with a place holder to identify the code block
        $replacement = "{{{" . $blockName . "}}}";
        return $replacement;
    }

    /**
     * Escher\Template::_new_code_block()
     *
     * @access private
     * @return array
     */
    private function emptyCodeBlock()
    {
        return array(
            "code" => "",
            "children" => array(),
            "variables" => array(),
            "segmentor" => array(),
        );
    }

    /**
     * Escher\Template::cacheValid()
     *
     * @access private
     * @param string $filename
     * @return bool
     */
    private function cacheValid($filename)
    {
        $cacheFilename = self::$cacheLocation . "/" . sha1(realpath($filename)) . self::CACHE_EXTENSION;
        if (file_exists($filename) && file_exists($cacheFilename)) {
            $fileIsNotModified = filemtime($filename) === filemtime($cacheFilename);
            if ($fileIsNotModified) {
                return YES;
            }
        }

        return NO;
    }

    /**
     * Escher\Template::cacheRead()
     *
     * @access private
     * @param string $filename
     * @return array - template from cache as a codeblock array, bool NO on error
     */
    private function cacheRead($filename)
    {
        $cacheFilename = self::$cacheLocation . "/" . sha1(realpath($filename)) . self::CACHE_EXTENSION;
        if (file_exists($cacheFilename)) {
            return unserialize(file_get_contents($cacheFilename));
        } else {
            return NO;
        }
    }

    /**
     * Escher\Template::cacheWrite()
     *
     * @param string $filename
     * @param array $data - codeblock wanting to be cached
     * @return bool
     */
    private function cacheWrite($filename, $data)
    {
        $cacheFilename = self::$cacheLocation . "/" . sha1(realpath($filename)) . self::CACHE_EXTENSION;
        return (bool)file_put_contents($cacheFilename, serialize($data)) && touch($cacheFilename, filemtime($filename));
    }

    /**
     * Escher\Template::getNamespace()
     *
     * @param string $nameSpace
     * @param array $codeBlock (optional, reference)
     * @return array - Reference to the passed codeBlock
     */
    private function &getNamespace($nameSpace, &$codeBlock = null)
    {
        // Break up the passed namespace so we can iterate through it
        $nameArray = explode(":", $nameSpace);

        // If no codeblock is passed, use the global block
        if ($codeBlock === null) {
            $codeBlockArray = &$this->codeBlocks;
        } else {
            $codeBlockArray = &$codeBlock;
        }

        // Iterate through the namespaces. As we iterate, we load each level
        // and check if the next level is available in the children for that
        // namespace. If it is, we continue to the next iteration. If it is
        // not, then we have been passed an invalid namespace.
        for ($i = 0, $l =  count($nameArray); $i < $l; $i += 1) {

            // Check the child is available
            if (isset($codeBlockArray["children"][$nameArray[$i]]) === NO) {
                $this->fault(self::ERROR_INVALID_NAMESPACE, $nameSpace);
            }

            // It is - remember for the next iteration
            $codeBlockArray = &$codeBlockArray["children"][$nameArray[$i]];
        }

        // Return the appropriate block
        return $codeBlockArray;
    }

    /**
     * Escher\Template::createNamespace()
     *
     * @access private
     * @param string $nameSpace
     * @return array - Reference to the newly created nameSpace in $this->codeBlocks
     */
    private function &createNamespace($nameSpace)
    {
        // Break up the namespace so we can iterate through it
        $nameArray = explode(":", $nameSpace);

        // The final element contains the name of the
        // new space we are creating
        $newNameSpace = array_pop($nameArray);

        // Load up the global codeblock
        $codeBlockArray =& $this->codeBlocks;

        // Iterate through the namespace and make sure that all the
        // relevant spaces exist before we bung our new space in the end
        for ($i = 0, $l = count($nameArray); $i < $l; $i += 1) {

            // If the element isn't listed in the childre, then we have
            // been passed an invalid parameters!
            if (isset($codeBlockArray["children"][$nameArray[$i]]) === NO) {
                $this->fault(self::ERROR_INVALID_NAMESPACE, $nameSpace);
            }

            // Load up the child ready for the next iteration
            $codeBlockArray = &$codeBlockArray["children"][$nameArray[$i]];
        }

        // $codeBlockArray now contains the final child element from nameArray.
        // Into this, we stick a new child, with our new name
        $codeBlockArray["children"][$newNameSpace] = $this->emptyCodeBlock();

        // Return a reference to this newly created child
        return $codeBlockArray["children"][$newNameSpace];
    }

    /**
     * Escher\Template::render()
     *
     * <b>Example</b>
     * <code>
     * $template = new Template();
     * $template->loadTemplate("page_test.html", "Main");
     * $template->assign(array("PageName" => "Test Page"), "Main");
     * echo $template->render("Main");
     * </code>
     *
     * @param string $nameSpace
     * @param string $cache - Experimental caching feature. The REQUEST_URI of this page, if we want to cache it
     * @return string - Parsed Template
     */
    public function render($nameSpace)
    {
        // Get a handle on this namespace
        $codeBlock =& $this->getNamespace($nameSpace);
        $value = $this->parseBlock($codeBlock);

        // Replace any string literals with temporary variables
        $value = preg_replace_callback(
            '/{{`([^`\n]*)`(?:\[(\w+)\])?}}/',
            function ($matches) {
                $tempname = sha1("literal:" . $matches[1]);
                $this->assign([$tempname => $matches[1]]);
                if (empty($matches[2])) {
                    return "{{" . $tempname. "}}";
                }
                return sprintf("{{%s[%s]}}", $tempname, $matches[2]);
            },
            $value
        );

        // Substitute variables
        if (empty($this->codeBlocks["variables"]) === NO) {
            $value = $this->nameReplace($this->codeBlocks["variables"], $value);
        }

        // Strip out unused elements
        if ($this->removeUnusedNames === YES) {
            $value = preg_replace('/{{{?[\w\.]+(\[\w+\])?}}}?/', "", $value);
        }

        // Strip out unused comments
        if ($this->removeHTMLComments === YES) {
            $value = preg_replace('/<!--@.*?-->/s', "", $value);
        }

        // Decode encoded stuff
        $this->decode($value);

        // Execute all the defined post processors
        foreach ($this->processorList as $i => $callback) {
            $value = call_user_func($callback, $value);
        }

        return $value;
    }

    /**
     * Escher\Template::encode()
     *
     * @access private
     * @param string $code
     * @return string - Encoded code to protect characters through the templater
     */
    private function encode(&$code)
    {
        $code = str_replace(array('"', "'"), array('__DQUOT__', '__QUOT__'), $code);
    }

    /**
     * Escher\Template::decode()
     *
     * @access private
     * @param string $code
     * @return string - Inverse of encode
     */
    private function decode(&$code)
    {
        $code = str_replace(array('__QUOT__', '__DQUOT__'), array("'", '"'), $code);
    }

    /**
     * Escher\Template::parseBlock()
     *
     * @access private
     * @param mixed $codeBlock
     * @return string - Value for that block and all it's children after being parsed
     */
    private function parseBlock(&$codeBlock)
    {
        // Only process vars if instructed to, and vars are available.
        if (isset($codeBlock["variables"]) && is_array($codeBlock["variables"])) {

            // Container for parsed data
            $value = "";

            // Check if the current element in the variables array
            // is itself an array. If so, we process it as a recordset
            if (is_array(current($codeBlock["variables"]))) {

                // Check for segments
                if (empty($codeBlock["segmentor"])) {
                    $dataSource =& $codeBlock["variables"];
                } else {
                    $segmentor = $codeBlock["segmentor"][0];
                    $dataSource = array();
                    foreach ($codeBlock["variables"] as $record) {
                        $dataSource[$record[$segmentor]] = $record;
                        $childDataSource[$record[$segmentor]][] = $record;
                    }
                }

                // Row counter and max rows
                $rowCount = 1;
                $dataSourceMax = count($codeBlock["variables"]);

                // Loop through the variables
                foreach ($dataSource as $segment => $record) {

                    if (empty($record)) {
                        continue;
                    }

                    // Substitude the variables into the codeblock
                    $rowValue = $this->nameReplace($record, $codeBlock["code"]);

                    // Add in the custom replacements
                    $find = array();
                    $replace = array();

                    // Row counter
                    $find[] = "{{templateRowNum}}";
                    $replace[] = $rowCount;

                    $find[] = "{{templateRowAlpha}}";
                    $replace[] = chr(64 + $rowCount);

                    // If there are children
                    if (empty($codeBlock["children"]) === NO) {

                        // Check for a child to receive data segments
                        $segmentedChild = null;
                        if (empty($codeBlock["segmentor"]) === NO) {
                            $segmentedChild = $codeBlock["segmentor"][1];
                        }

                        // Loop through the child blocks, parse them and substitute them
                        // into the parsed code.
                        foreach ($codeBlock["children"] as $blockName => $childCodeBlock) {
                            // If the child block was requested for segmentation,
                            // then also assignthe correct variable segment to it.
                            if ($segmentedChild === $blockName) {
                                $childCodeBlock["variables"] = $childDataSource[$segment];
                            }

                            $blockValue = $this->parseBlock($childCodeBlock);
                            $find[] = "{{{" . $blockName . "}}}";
                            $replace[] = $blockValue;
                        }
                    }

                    // Perform these operations
                    $rowValue = str_replace($find, $replace, $rowValue);

                    // Increment row counter
                    $rowCount += 1;

                    // Add onto the parsed data
                    $value .= $rowValue;
                }

                return $value;

            } else {

                // Not a recordset, just do a straight replace
                $value = $this->nameReplace($codeBlock["variables"], $codeBlock["code"]);
            }

        } else {

            // Don"t parse the vars, just use the raw code
            $value = $codeBlock["code"];
        }

        // If there are children
        if (empty($codeBlock["children"]) === NO) {

            $find = array();
            $replace = array();

            // Loop through the child blocks, parse them and substitute them
            // into the parsed code.
            foreach ($codeBlock["children"] as $blockName => $childCodeBlock) {
                $blockValue = $this->parseBlock($childCodeBlock);
                $find[] = "{{{" . $blockName . "}}}";
                $replace[] = $blockValue;
            }

            // Perform the switch
            $value = str_replace($find, $replace, $value);
        }

        // Return the parsed code
        return $value;
    }

    /**
     * Escher\Template::nameReplace()
     *
     * Searches for names that need replacing, including
     * names with masks applied to them
     *
     * @access private
     * @param array $variableList
     * @param string $code
     * @return string
     */
    private function nameReplace($variableList, $code)
    {
        // If there are variables to replace
        if (is_array($variableList)) {

            // Loop through the variable list
            foreach ($variableList as $variableName => $variableValue) {

                // If this is a callback, execute it first
                if (is_scalar($variableValue) === NO && is_callable($variableValue)) {
                    $variableValue = call_user_func($variableValue);
                }

                // If this is an escher view, render it first
                if ($variableValue instanceof View) {
                    $variableValue = $variableValue->render();
                }

                // Variables enclosed in {{{}}} should be replaced raw.
                $code = preg_replace_callback(
                    '/{{{(' . $variableName . ')(\\[(\\w+)\\])?}}}/',
                    function ($matches) use ($variableValue) {
                        if (isset($matches[3])) {
                            return $this->maskReplace($matches[3], $variableValue);
                        }

                        return $variableValue;
                    },
                    $code
                );

                // Variables enclosed in {{}} should be escaped first.
                $code = preg_replace_callback(
                    '/{{(' . $variableName . ')(\\[(\\w+)\\])?}}/',
                    function ($matches) use ($variableValue) {
                        $safeValue = htmlspecialchars($variableValue);
                        if (isset($matches[3])) {
                            return $this->maskReplace($matches[3], $safeValue);
                        }

                        return $safeValue;
                    },
                    $code
                );
            }
        }

        // Return the processed code
        return $code;
    }

    /**
     * Escher\Template::assign()
     *
     * Assigns a variable within the template namespace.
     * If no namespace is specified, then the variable it declared globally.
     *
     * <b>Example - Name Replacement</b>
     * <code>
     * $template = new Template();
     * $template->loadTemplate("page_test.html", "Main");
     * $template->assign(array("Name" => "Richard", "Age" => 20), "Main:Content:UserDetails");
     * echo $template-render("Main");
     * </code>
     *
     * <b>Example - Multi Row</b>
     * <code>
     * $template = new Template();
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
     *
     * If an invalid value for $dataValues is passed, a {@link VaildationException} will be thrown
     *
     * @param array $dataValues
     * @param string $nameSpace (optional) - The namespace within this template to place the variable in
     */
    public function assign($dataValues, $nameSpace = null)
    {
        if (is_null($nameSpace)) {

            // Null means get the top-level code block
            $nameSpace =& $this->codeBlocks;

        } elseif (is_array($nameSpace)) {

            // If the passed namespace is an array, then
            // it is defining segmentors for nested codeblocks

            // Rename the namespace
            $segmentors = $nameSpace;

            // Now load the real namespace for this data from the
            // reserved "_root" namespace
            $nameSpace =& $this->getNamespace($segmentors["_root"]);
            unset($segmentors["_root"]);

            // Iterate over any remaining segmentors
            if (sizeof($segmentors)) {
                foreach ($segmentors as $segmentor => $segmentorTarget) {
                    // Find the parent of the namespace identified by the segmentor
                    $child = substr($segmentorTarget, strrpos($segmentorTarget, ":") + 1);
                    $parent = substr($segmentorTarget, 0, -strlen($child) - 1);

                    // Load that parent and set a segmentor on it
                    $segmentorTargetBlock =& $this->getNamespace($parent);
                    $segmentorTargetBlock["segmentor"] = array($segmentor, $child);
                }
            }

        } else {

            // Otherwise, it is a string and defines
            // the name of the codeblock to load
            $nameSpace =& $this->getNamespace($nameSpace);
        }

        // Make sure the data is an array
        if ($dataValues instanceof MySQLi_Result) {

            while ($record = mysqli_fetch_assoc($dataValues)) {
                $nameSpace["variables"][] = $record;
            }

        } elseif (is_array($dataValues)) {

            // Loop through the data and store in the block
            foreach ($dataValues as $varName => $varValue) {
                if (is_string($varName)) {
                    if (is_array($varValue) && !is_callable($varValue)) {
                        $masterName = $varName;
                        $flattened = $this->flattenVariables($varValue);
                        foreach ($flattened as $varName => $varValue) {
                            $nameSpace["variables"]["$masterName.$varName"] = $varValue;
                        }
                    } else {
                        $nameSpace["variables"][$varName] = $varValue;
                    }
                } else {
                    $varValue = $this->flattenVariables($varValue);
                    $nameSpace["variables"][] = $varValue;
                }
            }

        } else {

            // Can't make sense of the passed data
            $this->fault(self::ERROR_NOT_VALID);
        }
    }

    private function flattenVariables($parent)
    {
        foreach ($parent as $parentKey => $parentValue) {
            if (is_array($parentValue)) {
                $parentValue = $this->flattenVariables($parentValue);
                foreach ($parentValue as $childKey => $childValue) {
                    $parent["$parentKey.$childKey"] = $childValue;
                }
                unset($parent[$parentKey]);
            }
        }

        return $parent;
    }

    /**
     * Escher\Template::removeBlock()
     *
     * Removes the codeBlock specifed though namespace from the template
     *
     * @access public
     * @param string $nameSpace
     */
    public function removeBlock($nameSpace)
    {
        // Break up the namespaces
        $child = substr($nameSpace, strrpos($nameSpace, ":") + 1);
        $parent = substr($nameSpace, 0, -strlen($child) - 1);

        // Load the parent code block
        $codeBlock = &$this->getNamespace($parent);

        // Kill this child
        unset($codeBlock["children"][$child]);
    }

    /**
     * Escher\Template::retrieveVar()
     *
     * Retrives all varibles that have been assign to the namespace
     *
     * @access public
     * @param string $nameSpace
     * @return array
     */
    public function retrieveVar($nameSpace)
    {
        $codeBlock =& $this->getNamespace($nameSpace);
        return $codeBlock["variables"];
    }

    public function retrieveChildren($nameSpace)
    {
        $codeBlock =& $this->getNamespace($nameSpace);
        return array_keys($codeBlock["children"]);
    }

    /**
     * Escher\Template::removeVar()
     *
     * Removes a single variable of the name provided, or if no variable name
     * is provided will remove all variables fom that namespace, to cascade into
     * any sub codeblock end your namespace with a : e.g. Main:Content:
     *
     * @access public
     * @param string $nameSpace - The namespace within this template that contains the item to be removed
     * @param string $variableName (optional) - The name of the variable to remove
     */
    public function removeVar($nameSpace, $variableName = "")
    {
        // Check if we need to cascade
        if (substr($nameSpace, -1) === ":") {
            $cascade = YES;
            $nameSpace = rtrim($nameSpace, ":");
        } else {
            $cascade = NO;
        }

        // Load up the codeblock
        $codeBlock = &$this->getNamespace($nameSpace);

        // With no variable name, we remove all vars
        if (empty($variableName)) {
            if ($cascade === YES) {
                $codeBlock = $this->cascadeRemoveVar($codeBlock);
            } else {
                $codeBlock["variables"] = array();
            }
        } else {
            // Just remove this var
            unset($codeBlock["variables"][$variableName]);
        }

        return YES;
    }

    /**
     * Escher\Template::cascadeRemoveVar()
     *
     * Removes all variables from a codeblock and all child variables from the same block
     *
     * @access private
     * @param string $codeBlock - The block to have its vars trashed
     * @return array - The processed codeBlock
     */
    private function cascadeRemoveVar($codeBlock)
    {
        $codeBlock["variables"] = array();
        foreach ($codeBlock["children"] as $childName => $cascadeCodeBlock) {
            $codeBlock["children"][$childName] = $this->cascadeRemoveVar($cascadeCodeBlock);
        }

        return $codeBlock;
    }

    /**
     * Escher\Template::fault()
     * @ignore
     */
    protected static function fault($errorNum = 0, $message = "")
    {
        switch ($errorNum) {
            case self::ERROR_INVALID_NAMESPACE:
                trigger_error("Invalid namespace: " . $message, E_USER_ERROR);
                break;
            case self::ERROR_FILE_NOT_FOUND:
                trigger_error("File not found: " . $message, E_USER_ERROR);
                break;
            case self::ERROR_NOT_VALID:
                trigger_error("Invalid data passed: " . $message, E_USER_ERROR);
                break;
            default:
                trigger_error("Unclassified error", E_USER_ERROR);
        }
    }
}
