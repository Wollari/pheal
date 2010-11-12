<?php
/*
 MIT License
 Copyright (c) 2010 Peter Petermann

 Permission is hereby granted, free of charge, to any person
 obtaining a copy of this software and associated documentation
 files (the "Software"), to deal in the Software without
 restriction, including without limitation the rights to use,
 copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the
 Software is furnished to do so, subject to the following
 conditions:

 The above copyright notice and this permission notice shall be
 included in all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 OTHER DEALINGS IN THE SOFTWARE.
*/

/**
 * class to implement EVE API RowSets
 */
class PhealRowSet extends ArrayObject
{
    /**
     * name of the rowset
     * @var string
     */
    public $_name;

    /**
     * initialize the rowset
     * @param SimpleXMLElement $xml
     */
    public function __construct($xml)
    {
       $this->_name = (String) $xml->attributes()->name;
       foreach($xml->row as $rowxml)
       {
           $row = array();
           foreach($rowxml->attributes() as $attkey => $attval)
           {
               $row[$attkey] = (String) $attval;
           }
           foreach($rowxml->children() as $child) // nested tags in rowset/row
           {
               $element= PhealElement::parse_element($child);
               $row[$element->_name] = $element;
           }
           $this->append(new PhealRowSetRow($row));
        }
    }
}

