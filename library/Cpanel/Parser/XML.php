<?php
/**
 * Cpanel_Parser_XML
 *
 * Copyright (c) 2011, cPanel, Inc.
 * All rights reserved.
 * http://cpanel.net
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *    * Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *    * Neither the name of cPanel, Inc. nor the
 *      names of its contributors may be used to endorse or promote products
 *      derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA,
 * OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 * EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Cpanel
 * @package   Cpanel_Parser
 * @author    David Neimeyer <david.neimeyer@cpanel.net>
 * @copyright Copyright (c) 2011, cPanel, Inc., All rights Reserved. (http://cpanel.net)
 * @license   http://sdk.cpanel.net/license/bsd.html BSD License
 * @version   0.1.0
 * @link      http://sdk.cpanel.net
 * @since     0.1.0
 */
/**
 * XML Parser
 *
 * @class     Cpanel_Parser_XML
 * @category  Cpanel
 * @package   Cpanel_Parser
 * @author    David Neimeyer <david.neimeyer@cpanel.net>
 * @copyright Copyright (c) 2011, cPanel, Inc., All rights Reserved. (http://cpanel.net)
 * @license   http://sdk.cpanel.net/license/bsd.html BSD License
 * @version   0.1.0
 * @link      http://sdk.cpanel.net
 * @since     0.1.0
 */
class Cpanel_Parser_XML extends Cpanel_Core_Object implements Cpanel_Parser_Interface
{
    /**
     * Default DOMDocument XML decode mode
     * NOTE: root node will not be preserved in array representation
     */
    const DOM_MODE = 1;
    /**
     * Alternative DOMDocument XML decode mode
     * NOTE: root XML node will be preserved as the first element of the array
     * representation
     */
    const DOM_MODE_EXTENDED = 2;
    /**
     * Alternative SimpleXML XML decode mode
     * NOTE: root node will not be preserved in array representation
     */
    const SIMPLEXML_MODE = 3;
    /**
     * Response format type that this parser can encode/decode
     */
    const PARSER_TYPE = 'XML';
    /**
     * Generic prefix for parser error messaging for DOM decoder
     */
    const ERROR_DOM = 'DOMDocument - ';
    /**
     * Generic prefix for parser error messaging for SimpleXML decoder
     */
    const ERROR_SIMPLEXML = 'SimpleXML - ';
    /**
     * Container for recursive DOM node build
     */
    private $_dom;
    /**
     * Observed encode/decode error
     */
    private $_hasParseError;
    /**
     * Constructor
     *
     * By default, Cpanel_Parser_XML::DOM_MODE will be set.  As well,
     * all errors warnings generated by LibXML will be suppressed.  This errors
     * well be collected later, in the event of a decode error.
     *
     * @param arrays $optsArray Optional configuration data
     *
     * @return Cpanel_Parser_XML
     */
    public function __construct($optsArray = array())
    {
        parent::__construct($optsArray);
        $libErrMode = (bool)!$this->disableSuppressLibXML;
        libxml_use_internal_errors($libErrMode);
        $mode = $this->mode;
        $this->setMode(self::DOM_MODE);
        if ($mode) {
            $this->setMode($mode);
        }
        return $this;
    }
    /**
     * Determine if an RFT can be parsed with this parser
     *
     * @param string $type The name of a response format type to evaluate
     *
     * @see    Cpanel_Parser_Interface::canParse()
     *
     * @return bool   Whether this parser can parse a sting of $type
     */
    public function canParse($type)
    {
        return (strtolower($type) == 'xml') ? true : false;
    }
    /**
     * Parse a string into an array structure
     *
     * By default, Cpanel_Parser_XML::DOM_MODE will be used to decode
     * $str.  Alternative decode modes can be set prior to parsing via
     * {@link setMode()}
     *
     * NOTE: After basic validation, if the parser cannot successfully parse
     * $str, internal property "_hasParseError" will be set to true.  An error
     * will NOT be throw.  This is to ensure a premature exit does not occur
     * since the query (likely) succeeded.  Problem as this level are ambiguous,
     * and therefore left to the invoking script/application to manage.
     *
     * @param string $str String to parse
     *
     * @see    Cpanel_Parser_XML::getParserInternalErrors
     * @see    Cpanel_Parser_Interface::parse()
     *
     * @return array|string Array representation of string on success,
     *                      otherwise a string expressing error.
     * @throws Exception If $str is not a string
     */
    public function parse($str)
    {
        if (!is_string($str)) {
            throw new Exception('Input must be a raw response string');
        }
        $mode = $this->mode;
        if ($mode == self::SIMPLEXML_MODE) {
            $rsXMLObj = $this->strToSimpleXML($str);
            if ($this->_hasParseError == true) {
                $prefix = self::ERROR_SIMPLEXML;
                return $this->getParserInternalErrors($prefix);
            }
            $r = $this->simpleXMLToArray($rsXMLObj);
        } else {
            $tdom = $this->strToDOM($str);
            if ($this->_hasParseError == true) {
                $prefix = self::ERROR_DOM;
                return $this->getParserInternalErrors($prefix);
            }
            $r = $this->DOMtoArray($tdom);
            //note: empty xml root node response is empty str; coerce to array
            if ($mode != self::DOM_MODE_EXTENDED && is_array($r) && !empty($r)) {
                $r = array_shift($r);
            }
        }
        return (is_array($r)) ? $r : array();
    }
    /**
     * Encode array structure into this parser's format type
     *
     * Encoding is only performed with DOMDocument.  Returned XML string will
     * be formated with line breaking and whitespace characters.
     *
     * @param Cpanel_Query_Object $obj Response object containing data to
     *  encode
     *
     * @see    Cpanel_Parser_Interface::encodeQueryObject()
     *
     * @return string              XML document
     * @throws Exception If $obj is an invalid response object
     */
    public function encodeQueryObject($obj)
    {
        if (!($obj instanceof Cpanel_Query_Object)) {
            throw new Exception('Parser can only encode known query object');
        }
        $arr = $obj->getResponse('array');
        $this->_dom = new DOMDocument('1.0');
        $this->_dom->preserveWhiteSpace = false;
        $this->_dom->formatOutput = true;
        // TODO: add error detection for empty arr
        if (is_array($arr) && count($arr) > 1) {
            $root = $this->_dom->createElement('result');
            $this->_dom->appendChild($root);
            $this->_recurse_node_build($arr, $root);
        } elseif (is_array($arr) && count($arr) === 1) {
            $this->_recurse_node_build($arr, $this->_dom);
        }
        $r = $this->_dom->saveXML();
        unset($this->_dom);
        return $r;
    }
    /**
     * Recursive build DOMDocument from array
     *
     * NOTE: because objects are passed by reference, $obj will not be returned
     *
     * Credit to Matt Wiseman (trollboy at shoggoth.net) for recursion schema
     *
     * @param array                  $data Array to convert to DOMElements
     * @param DOMDocument|DOMElement $obj  DOMDocument|DOMElement to append to
     *
     * @return void
     */
    private function _recurse_node_build($data, $obj)
    {
        // TODO: add error detection
        $i = 0;
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (in_array(0, array_keys($value), true)) {
                    foreach ($value as $v) {
                        if (is_array($v)) {
                            $sub_obj[$i] = $this->_dom->createElement($key);
                            $obj->appendChild($sub_obj[$i]);
                            $this->_recurse_node_build($v, $sub_obj[$i]);
                        } else {
                            $sub_obj[$i] = $this->_dom->createElement($key, $v);
                            $obj->appendChild($sub_obj[$i]);
                        }
                        $i++;
                    }
                } else {
                    $sub_obj[$i] = $this->_dom->createElement($key);
                    $obj->appendChild($sub_obj[$i]);
                    $this->_recurse_node_build($value, $sub_obj[$i]);
                }
            } else {
                $sub_obj[$i] = $this->_dom->createElement($key, $value);
                $obj->appendChild($sub_obj[$i]);
            }
            $i++;
        }
    }
    /**
     * Import XML string into new DOMDocument
     *
     * @param string $str XML string to import
     *
     * @return DOMDocument
     */
    protected function strToDOM($str)
    {
        libxml_clear_errors();
        $dom = new DOMDocument('1.0');
        $r = $dom->loadXML($str);
        if ($r === false) {
            $this->_hasParseError = true;
            return $r;
        }
        return $dom;
    }
    /**
     * Convert DOMDocument into PHP array, recursing as necessary
     *
     * @param DOMDocument|DOMElement $node DOMDocument|DOMElement to recurse
     *
     * @return array                  PHP array representation of $node
     */
    protected function DOMtoArray($node)
    {
        $result = array();
        $children = $node->childNodes;
        if ($children->length == 1) {
            $child = $children->item(0);
            if ($child->nodeType == XML_TEXT_NODE) {
                return $child->nodeValue;
            }
        } elseif ($children->length < 1) {
            return $node->nodeValue;
        }
        $group = array();
        for ($i = 0; $i < $children->length; $i++) {
            $child = $children->item($i);
            if ($child->nodeName != '#text' && $child->nodeName != '#comment') {
                if (!isset($result[$child->nodeName])) {
                    $result[$child->nodeName] = $this->DOMtoArray($child);
                } else {
                    if (!isset($group[$child->nodeName])) {
                        $tmp = $result[$child->nodeName];
                        $result[$child->nodeName] = array(
                            $tmp
                        );
                        $group[$child->nodeName] = 1;
                    }
                    $result[$child->nodeName][] = $this->DOMtoArray($child);
                }
            }
        }
        return $result;
    }
    /**
     * Import XML string into new SimpleXML object
     *
     * @param string $str XML string to import
     *
     * @return SimpleXMLElement
     */
    protected function strToSimpleXML($str)
    {
        $str = trim($str);
        libxml_clear_errors();
        $sXMLObj = simplexml_load_string($str);
        if ($sXMLObj === false) {
            $this->_hasParseError = true;
            return $sXMLObj;
        }
        return $sXMLObj;
    }
    /**
     * Convert SimpleXMLElement into PHP array, recursing as necessary
     *
     * @param SimpleXMLEement $input   SimpleXMLElement to recurse
     * @param bool            $recurse Whether this invocation is recursive
     *
     * @return array           PHP array representation of $input
     */
    protected function simpleXMLToArray($input, $recurse = false)
    {
        // Loading xml string with simplexml if its the top level of recursion
        $data = ((!$recurse) && is_string($input)) ? simplexml_load_string($input) : $input;
        // Convert SimpleXMLElements to array
        if ($data instanceof SimpleXMLElement) {
            $data = (array)$data;
            if (empty($data)) {
                $data = '';
            }
        }
        // Recurse into arrays
        if (is_array($data)) {
            foreach ($data as & $item) {
                $item = $this->simpleXMLToArray($item, true);
            }
        }
        return $data;
    }
    /**
     * Generate an error string to bubble up
     *
     * If LibXML errors have previously been suppress, libxml_get_errors() will
     * be invoked to fetch them.
     *
     * @param string $prefix  A string to prefix the returned error string for
     *  contextual reference
     * @param string $default A default error message if one can not be
     *  determined (from the native PHP LibXML error functions)
     *
     * @see    Cpanel_Parser_Interface::getParserInternalErrors()
     *
     * @return string String detailing an error has occurred
     */
    public function getParserInternalErrors($prefix = '', $default = 'Could not load string.')
    {
        $errmsg = '';
        if ($this->_hasParseError) {
            $errmsg.= $prefix;
            $libmsg = '';
            if (!$this->disableSuppressLibXML) {
                $errstrs = array();
                foreach (libxml_get_errors() as $err) {
                    $errstrs[] = trim($err->message);
                }
                libxml_clear_errors();
                $libmsg.= implode('. ', $errstrs);
            }
            if (empty($libmsg)) {
                $errmsg.= $default;
            } else {
                $errmsg.= $libmsg;
            }
        }
        return $errmsg;
    }
    /**
     * Set special encode mode
     *
     * This parser supports use of DOM or SimpleXML.  This primarily only has
     * importance when iterating over interpreted response. i.e., in the legacy
     * XML-API PHP client class, when a (SimpleXML) object was returned, the
     * object was iterated starting at the docroot's first element.  With the
     * introduction of Cpanel_Parser_XML::DOM_MODE_EXTENDED it is
     * possible to iterate at the document's root element itself.  i.e
     * ( $obj->firstchild vs. $obj->rootnode->childnode)
     *
     * @param int $flag Constant value to set encoding mode to
     *
     * @see    Cpanel_Parser_XML::DOM_MODE
     * @see    Cpanel_Parser_XML::DOM_MODE_EXTENDED
     * @see    Cpanel_Parser_XML::SIMPLEXML_MODE
     *
     * @return Cpanel_Parser_XML
     */
    public function setMode($flag)
    {
        if (empty($flag) || !is_int($flag)) {
            $this->mode = self::DOM_MODE;
        } elseif ($flag == self::DOM_MODE
               || $flag == self::DOM_MODE_EXTENDED
               || $flag == self::SIMPLEXML_MODE) {
            $this->mode = $flag;
        }
        return $this;
    }
}
?>
