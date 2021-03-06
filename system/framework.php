<?php
/**
 * Framework of Flupp CMS
 * @package Flupp CMS 
 * @author  Christian Klisch
 * @since   0.0.1
 */
namespace framework;

require "vendor/crodas/haanga/lib/Haanga.php";
require_once (SYSTEM . 'modules/abstractModule.php');

class Framework
{

    protected $slimApp;

    protected $modules = array();

    protected $config;

    protected $content;

    protected $parsedown;

    public static function autoload($className)
    {
        $thisClass = str_replace(__NAMESPACE__ . '\\', '', __CLASS__);
        $baseDir = __DIR__;
        
        if (substr($baseDir, - strlen($thisClass)) === $thisClass) {
            $baseDir = substr($baseDir, 0, - strlen($thisClass));
        }
        
        $className = ltrim($className, '\\');
        $fileName = $baseDir;
        $namespace = '';
        
        if ($lastNsPos = strripos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName .= str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
        
        if (file_exists($fileName)) {
            require $fileName;
        }
    }

    public static function registerAutoloader()
    {
        spl_autoload_register(__NAMESPACE__ . "\\framework::autoload");
    }

    public function __construct($app)
    {
        $this->parsedown = new \Parsedown();
        
        $this->slimApp = $app;
        $this->generateCache();
        
        $this->loadModules();
    }

    private function configReader()
    {
        $config['system'] = spyc_load_file(CONFIG);
        $config['modules'] = $this->readModules();
        
        return $config;
    }

    private function readModules($path = null, &$yaml = array())
    {
        if ($path == null)
            $path = '';
        if ($yaml == null)
            $yaml = array();
            
            // search in subdirectories
        $dirs = array_filter(glob(MODULES . $path . '*'), 'is_dir');
        
        foreach ($dirs as $filename) {
            if (is_dir($filename) && $filename != '.' && $filename != '..') {
                $this->readModules($path . basename($filename) . '/', $yaml);
            }
        }
        
        // create/merge configs
        $yaml['/' . $path] = \Spyc::YAMLLoad(MODULES . $path . CONFIG);
        
        return $yaml;
    }

    private function readContents($path = null, &$contents = array())
    {
        if ($path == null)
            $path = '';
        if ($contents == null)
            $contents = array();
            
            // search in subdirectories
        $files = glob(CONTENT . $path . '*');
        
        foreach ($files as $filename) {
            if (is_dir($filename) && $filename != '.' && $filename != '..') {
                $this->readContents($path . basename($filename) . '/', $contents);
            }
            
            if (! is_dir($filename)) {
                $filecontent = trim(file_get_contents($filename));
                $filecontent = str_replace("\r\n", "\n", $filecontent);
                
                $contentparts = explode("\n\n", $filecontent, 2);
                $metacontent = \Spyc::YAMLLoadString($contentparts[0]);
                $textcontent = '';
                
                if (sizeof($contentparts) > 1)
                    $textcontent = $contentparts[1];
                
                $filearray = array();
                $filearray[] = $metacontent;
                $metacontent['content'] = $textcontent;
                
                $contents['/' . $path][basename($filename, EXTENSION)] = $metacontent;
            }
        }
        
        return $contents;
    }

    public function generateCache()
    {
        $cache = new \PHPCache(array(
            'debug' => true,
            'cacheDir' => CACHE,
            'cacheTime' => 1
        ));
        
        $key = 'configuration';
        if (is_writable(CACHE . $key))
            $this->config = $cache->cacheVal($this->configReader(), $key);
        else
            $this->config = $this->configReader();
        
        $key = 'content';
        if (is_writable(CACHE . $key))
            $this->content = $cache->cacheVal($this->readContents(), $key);
        else
            $this->content = $this->readContents();
    }

    public function perform($path)
    {
        $tmpUrl = "";
        
        if ($_SERVER['REQUEST_URI'][strlen($_SERVER['REQUEST_URI']) - 1] == '/')
            $tmpUrl = $_SERVER['REQUEST_URI'];
        else
            $tmpUrl = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/') + 1);
        
        $html = "";
        
        $pathstring = implode($path, '/');
        $moduleString = $this->findModuleInPath($path);
        $contentString = $this->findContentInPath($path);
        $targetSite = $this->findSiteInPath($pathstring);
        
        $siteNo = '';
        if (is_numeric($targetSite)) {
            $siteNo = $targetSite;
            $targetSite = 'index';
        }
        
        $scriptUrl = str_replace(rtrim($moduleString, '/'), '', $tmpUrl);
        
        $values = array();
        $values['main'] = 'NOT FOUND';
        $values['title'] = $targetSite;
        $values['meta_description'] = '';
        $values['navigation'] = null;
        $values['breadcrumb'] = null;
        
        $module = $this->modules[$moduleString];
        $module->setSettings($this->config['modules'][$moduleString], $moduleString);
        
        if ($module != null) {
            $moduleHtml = "";
            
            // references
            if ($module != null && $module->getReferences() != null && sizeof($module->getReferences()) > 0 && $targetSite != 'index') {
                $refs = $module->getReferences();
                
                foreach ($refs as $refKey => $refVal) {
                    
                    $values[$refKey] = null;
                    
                    if (substr($refVal, - 1) != '/')
                        $refVal .= '/';
                    
                    if (array_key_exists($refKey, $this->content[$contentString][$targetSite])) {
                        $siteReferences = $this->content[$contentString][$targetSite][$refKey];
                        
                        if (! is_array($siteReferences))
                            $siteReferences = explode(" ", $siteReferences);
                        
                        foreach ($siteReferences as $siteReference) {
                            // $values[$refKey][$this->content[$contentString][$targetSite]['title']] = $refVal . $siteReference;
                            if (array_key_exists($refVal, $this->content))
                                $values[$refKey][$this->content[$refVal][$siteReference]['title']] = $refVal . $siteReference;
                            else
                                $values[$refKey][$siteReference] = $refVal . $siteReference;
                        }
                    }
                    
                    if (array_key_exists($refKey, $values) && $values[$refKey] != NULL)
                        $values[$refKey] = $this->addNavigationUrlPrefix($values[$refKey], $this->config['system']['url']);
                }
            }
            
            if (! array_key_exists($targetSite, $this->content[$contentString]) && $targetSite == 'index') {
                $moduleHtml = $module->output('index', $this->content[$contentString], $siteNo, $tmpUrl, $values, $this->content, $this->config['system']['url']);
                $values['main'] = $moduleHtml;
                
                if ($module->getNavigation() != null && sizeof($module->getNavigation()) > 0 && $values['navigation'] == null)
                    $values['navigation'] = $module->getNavigation();
                
                foreach ($module->getFields() as $field) {
                    if ($field != 'content')
                        $values[$field] = '';
                }
            } else {
                // submodules outputs
                $subs = array();
                foreach ($module->getSubviews() as $sub => $subview) {
                    $subs[$sub] = $this->performRecursive($moduleString . $subview, $tmpUrl, $values);
                }
                
                $moduleHtml = $module->output('view', $this->content[$contentString], $targetSite, $tmpUrl, $values, $this->content, $this->config['system']['url']);
                
                // mix subs
                
                $templatesub = \Haanga::compile($moduleHtml);
                $moduleHtml = $templatesub($subs);
                
                $values['main'] = $moduleHtml;
                
                foreach ($module->getFields() as $field) {
                    if (array_key_exists($targetSite, $this->content[$contentString]))
                        if (array_key_exists($field, $this->content[$contentString][$targetSite]) && $field != 'content')
                            $values[$field] = $this->content[$contentString][$targetSite][$field];
                }
            }
        }
        
        // if navigation not found
        if ($values['navigation'] == null) {
            $navModPath = $moduleString;
            while (stripos($navModPath, '/') >= 0 && sizeof($navModPath) > 0 && $navModPath != '') {
                
                if (substr("$navModPath", - 1) != '/')
                    $navModPath .= '/';
                
                if ($navModPath != null && array_key_exists($navModPath, $this->modules) && $this->modules[$navModPath] != null && $this->modules[$navModPath]->getNavigation() != null && $values['navigation'] == null)
                    $values['navigation'] = $this->modules[$navModPath]->getNavigation();
                
                $navModPath = rtrim($navModPath, '/');
                $navModPath = substr($navModPath, 0, strrpos($navModPath, '/'));
            }
        }
        if ($values['navigation'] == null) {
            if (array_key_exists('/', $this->modules) && $this->modules['/'] != null && $this->modules['/']->getNavigation() != null)
                $values['navigation'] = $this->modules['/']->getNavigation();
        }
        
        if ($values['navigation'] != null)
            $values['navigation'] = $this->addNavigationUrlPrefix($values['navigation'], $this->config['system']['url']);
            
            // breadcrumb
        if ($values['breadcrumb'] == null) {
            $navModPath = $moduleString;
            $navUrlPath = '/' . ltrim(str_replace($this->config['system']['url'], '', $tmpUrl), '/');
            
            while (stripos($navModPath, '/') >= 0 && sizeof($navModPath) > 0 && $navModPath != '') {
                
                if (substr("$navModPath", - 1) != '/')
                    $navModPath .= '/';
                if (substr("$navUrlPath", - 1) != '/')
                    $navUrlPath .= '/';
                
                if (substr($navModPath, - 6) == '/this/') {
                    $tp = rtrim($navUrlPath, '/');
                    $tmod = $this->findModuleInPath(explode('/', $tp));
                    $tsite = $this->findSiteInPath($tp);
                    
                    $values['breadcrumb'][ltrim($this->content[$tmod][$tsite]['title'], '/')] = $tp;
                } else
                    $values['breadcrumb'][$this->modules[$navModPath]->getName()] = $navUrlPath;
                
                $navModPath = rtrim($navModPath, '/');
                $navModPath = substr($navModPath, 0, strrpos($navModPath, '/'));
                
                $navUrlPath = rtrim($navUrlPath, '/');
                $navUrlPath = substr($navUrlPath, 0, strrpos($navUrlPath, '/'));
            }
            $values['breadcrumb'][$this->modules['/']->getName()] = '/';
            $values['breadcrumb'] = array_reverse($values['breadcrumb']);
            $values['breadcrumb'] = $this->addNavigationUrlPrefix($values['breadcrumb'], $this->config['system']['url']);
        }
        
        // select layout
        $footer = file_get_contents(THEMES . $this->config['system']['theme']['name'] . '/footer.html');
        $header = file_get_contents(THEMES . $this->config['system']['theme']['name'] . '/header.html');
        
        if (is_file(THEME . $this->config['system']['theme']['footer']))
            $footer = file_get_contents(THEME . $this->config['system']['theme']['footer']) . $footer;
        if (is_file(THEME . $this->config['system']['theme']['footer']))
            $header .= file_get_contents(THEME . $this->config['system']['theme']['header']);
        
        $values['footer'] = $footer;
        $values['header'] = $header;
        
        // css & meta
        $meta = '';
        $files = glob(THEMES . $this->config['system']['theme']['name'] . '/*.css');
        foreach ($files as $filename) {
            if (is_file($filename)) {
                $meta .= '<link rel="stylesheet" href="' . $this->config['system']['url'] . '/' . basename(THEMES) . '/' . $this->config['system']['theme']['name'] . '/' . basename($filename) . '">';
            }
        }
        $files = glob(THEMES . $this->config['system']['theme']['name'] . '/*.js');
        foreach ($files as $filename) {
            if (is_file($filename)) {
                $meta .= '<script  href="' . $this->config['system']['url'] . '/' . basename(THEMES) . '/' . $this->config['system']['theme']['name'] . '/' . basename($filename) . '"></script>';
            }
        }
        
        $values['meta'] = $meta;
        
        $template = \Haanga::compile(file_get_contents(THEMES . $this->config['system']['theme']['name'] . '/' . $module->getLayout()));
        return $template($values);
    }

    private function performRecursive($pathstring, $url, $values)
    {
        // echo "performRecursive($pathstring, $url)<br/>\n";
        $pathstring = trim($pathstring, '/');
        $html = "";
        
        $moduleString = $this->findModuleInPath(explode('/', $pathstring));
        $targetSite = $this->findSiteInPath($pathstring);
        
        if ($values == null)
            $values = array();
        
        $module = $this->modules[$moduleString];
        
        $url .= ltrim($moduleString, '');
        $url = str_replace('//', '/', $url);
        
        if ($module != null) {
            $moduleHtml = "NOT FOUND";
            if (! array_key_exists($targetSite, $this->content[$moduleString]) && $targetSite == 'index') {
                
                $moduleHtml = $module->output('index', $this->content[$moduleString], '', $url, $values, $this->content);
                
                foreach ($module->getFields() as $field) {
                    if ($field != 'content')
                        $values[$field] = '';
                }
            } else {
                
                $moduleHtml = $module->output('view', $this->content[$moduleString], $targetSite, $url, $values, $this->content);
                
                foreach ($module->getFields() as $field) {
                    if (array_key_exists($field, $this->content[$moduleString][$targetSite]) && $field != 'content')
                        $values[$field] = $this->content[$moduleString][$targetSite][$field];
                }
            }
            
            $template = \Haanga::compile($moduleHtml);
            $html = $template($values);
        }
        return $html;
    }

    private function loadModules()
    {
        foreach ($this->config['modules'] as $key => $modConf) {
            
            $files = glob(MODULES . $key . "*.php");
            
            if (is_dir(SYSTEM . 'modules/' . lcfirst($modConf['classname']))) {
                include_once SYSTEM . 'modules/' . lcfirst($modConf['classname']) . '/' . lcfirst($modConf['classname']) . '.php';
                
                $classname = $modConf['classname'];
                $this->modules[$key] = new $classname($modConf);
                $this->setModulePaths($key, SYSTEM . 'modules/' . lcfirst($modConf['classname']) . '/');
            } else {
                foreach (glob(MODULES . $key . "*.php") as $filename) {
                    include_once $filename;
                    
                    $classname = $modConf['classname'];
                    $this->modules[$key] = new $classname($modConf);
                    $this->setModulePaths($key, dirname($filename) . '/');
                }
            }
            
            $tmpPath = "";
        }
    }

    private function setModulePaths($key, $path)
    {
        $views = $this->modules[$key]->getViews();
        $newViews = array();
        
        foreach ($views as $key2 => $view)
            $newViews[$key2] = $path . $view;
        
        $this->modules[$key]->setViews($newViews);
    }

    private function findModuleInPath($path)
    {
        // echo "findModuleInPath()<br/>\n";
        // print_r($this->content);
        $actPath = '';
        $selectedModule = '';
        $i = 1;
        $lastPath = '';
        
        foreach ($path as $file) {
            
            $directThis = false;
            $actPath .= $file;
            $evtlSite = $actPath;
            
            // if (array_key_exists('/' . $actPath . '/', $this->content) && $i != sizeof($path)) {
            if ($i != sizeof($path)) {
                if (array_key_exists('/' . $lastPath . '/this/', $this->modules))
                    $directThis = true;
                
                $actPath .= '/';
                
                if ($directThis)
                    $selectedModule .= '/this';
                else
                    $selectedModule .= '/' . $file;
            }
            $i ++;
            $lastPath = $evtlSite;
        }
        
        return $selectedModule . '/';
    }

    private function findContentInPath($path)
    {
        $actPath = '';
        $selectedModule = '';
        $i = 1;
        $lastPath = '';
        
        foreach ($path as $file) {
            
            $directThis = false;
            $actPath .= $file;
            $evtlSite = $actPath;
            
            if (array_key_exists('/' . $actPath . '/', $this->content) && $i != sizeof($path)) {
                
                // if (array_key_exists('/' . $lastPath . '/this/', $this->modules))
                // $directThis = true;
                
                $actPath .= '/';
                
                if ($directThis)
                    $selectedModule .= '/this';
                else
                    $selectedModule .= '/' . $file;
            }
            $i ++;
            $lastPath = $evtlSite;
        }
        
        return $selectedModule . '/';
    }

    private function findModuleInPathO($pathstring)
    {
        return substr($pathstring, 0, strrpos($pathstring, '/'));
    }

    private function findSiteInPath($pathstring)
    {
        // echo "findSiteInPath($pathstring)<br/>\n";
        if ($pathstring == '/' || $pathstring == '')
            return 'index';
        
        if (strrpos($pathstring, '/') > 0) {
            $mod1 = strrchr($pathstring, '/');
            
            if (substr($mod1, 0, 1) == '/')
                $mod1 = substr($mod1, 1);
            
            if ($mod1 == '')
                $mod1 = 'index';
            
            return $mod1;
        } else
            return $pathstring;
    }

    private function addNavigationUrlPrefix($navigation, $prefix)
    {
        foreach ($navigation as $key => $val)
            $navigation[$key] = $prefix . $val;
        return $navigation;
    }
}

\framework\Framework::registerAutoloader();

$app = new \Slim\Slim(array(
    'templates.path' => '/'
));

$fw = new \framework\Framework($app);

$app->get('/', function () use($app, $fw)
{
    echo $fw->perform(array(
        '/'
    ));
});

$app->get('/cache', function () use($app, $fw)
{
    $fw->generateCache();
    $app->render('test.html');
});

$app->get('/:name+', function ($path) use($app, $fw)
{
    echo $fw->perform($path);
});

$app->run();     
