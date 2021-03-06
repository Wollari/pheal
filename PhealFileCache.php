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
 * Simple filecache for the xml
 */
class PhealFileCache implements PhealCacheInterface
{
    /**
     * path where to store the xml
     * @var string
     */
    protected $basepath;

    /**
     * various options for the filecache
     * valid keys are: delimiter, umask, umask_directory
     * @var array
     */
    protected $options = array(
        'delimiter' => ':',
        'umask' => 0666,
        'umask_directory' => 0777,
        'md5' => false
    );

    /**
     * construct PhealFileCache,
     * @param string $basepath optional string on where to store files, defaults to the current/users/home/.pheal/cache/
     * @param array $options optional config array, valid keys are: delimiter, umask, umask_directory
     */
    public function __construct($basepath = false, $options = array())
    {
        if(!$basepath)
            $basepath = getenv('HOME'). "/.pheal/cache/";
        $this->basepath = $basepath;

        // Windows systems don't allow : as part of the filename
        $this->options['delimiter'] = (strtoupper (substr(PHP_OS, 0,3)) == 'WIN') ? "#" : ":";

        // add options
        if(is_array($options) && count($options))
            $this->options = array_merge($this->options, $options);
    }

    /**
     * create a filename to use
     * @param int $userid
     * @param string $apikey
     * @param string $scope
     * @param string $name
     * @param array $args
     * @return string
     */
    protected function filename($userid, $apikey, $scope, $name, $args)
    {
        // secure input to make sure pheal don't write the files anywhere
        // user can define their own apikey/vcode
        // maybe this should be tweaked or hashed
        $regexp = "/[^a-z0-9,.-_=]/i";
        $userid = (int)$userid;
        $apikey = preg_replace($regexp,'_',$apikey);
        $md5 = $this->options['md5'];
        
        // build cache filename
        $argstr = "";
        foreach($args as $key => $val) {
            if(strlen($val) < 1)
                unset($args[$key]);
            elseif(!in_array(strtolower($key), array('userid','apikey','keyid','vcode')))
                $argstr .= preg_replace($regexp,'_',$key) . $this->options['delimiter'] . preg_replace($regexp,'_',$val) . $this->options['delimiter'];
        }
        
        // if argument list is too long (filesystem limitation) auto-md5 arguments
        if(strlen($argstr) > 100)
            $md5 = true;
            
        // create final filename / path
        $argstr = substr($argstr, 0, -1);
        $filename = "Request" . ($argstr ? "_" . ($md5 ? md5($argstr) : $argstr) : "") . ".xml";
        $filepath = $this->basepath . ($userid ? "$userid/$apikey/$scope/$name/" : "public/public/$scope/$name/");
        
        return $filepath . $filename;
    }

    /**
     * prepare directory for cache usage
     * @param string $filename complete path+filename
     */
    protected function prepare_path($filename)
    {
        // extract path+file
        $path = dirname($filename);
        $file = basename($filename);
        
        // check if it's a new path which must be created
        if(!file_exists($path)) {
            // check write access
            if(!is_writable($this->basepath))
                throw new PhealException(sprintf("Cache directory '%s' isn't writeable", $path));

            // create cache folder
            $oldUmask = umask(0);
            mkdir($path, $this->options['umask_directory'], true);
            umask($oldUmask);

        // path already exists
        } else {
            // check write access for the directory
            if(!is_writable($path))
                throw new PhealException(sprintf("Cache directory '%s' isn't writeable", $path));
            // check if the cache file is writeable (if exists)
            if(file_exists($file) && !is_writeable($file))
                throw new PhealException(sprintf("Cache file '%s' isn't writeable", $file));
        }
    }

    /**
     * Load XML from cache
     * @param int $userid
     * @param string $apikey
     * @param string $scope
     * @param string $name
     * @param array $args
     */
    public function load($userid, $apikey, $scope, $name, $args)
    {
        $filename = $this->filename($userid, $apikey, $scope, $name, $args);
        if(!file_exists($filename))
            return false;
        $xml = join('', file($filename));
        if($this->validate_cache($xml))
            return $xml;
        return false;

    }

    /**
     * validate the cached xml if it is still valid. This contains a name hack
     * to work arround EVE API giving wrong cachedUntil values
     * @param string $xml
     * @return boolean
     */
    public function validate_cache($xml)
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set("UTC");

        $xml = new SimpleXMLElement($xml);
        $dt = (int) strtotime($xml->cachedUntil);
        $time = time();

        date_default_timezone_set($tz);

        return (bool) ($dt > $time);
    }
    
    /**
     * Save XML from cache
     * @param int $userid
     * @param string $apikey
     * @param string $scope
     * @param string $name
     * @param array $args
     * @param string $xml
     */
    public function save($userid,$apikey,$scope,$name,$args,$xml) 
    {
        $filename = $this->filename($userid, $apikey, $scope, $name, $args);
        $this->prepare_path($filename);
        $exists = file_exists($filename);
        
        // save content
        file_put_contents($filename, $xml);
        
        // chmod only new files
        if(!$exists)
            chmod($filename, $this->options['umask']);
    }
}
