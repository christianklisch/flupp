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
            'cacheTime' => 10
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
            $tmpUrl = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/'));
        
        $html = "";
        
        $pathstring = implode($path, '/');
        $moduleString = $this->findModuleInPath($path);
        $contentString = $this->findContentInPath($path);
        $targetSite = $this->findSiteInPath($pathstring);
        
        $scriptUrl = str_replace(rtrim($moduleString, '/'), '', $tmpUrl);
        
        $values = array();
        $values['main'] = 'NOT FOUND';
        $module = $this->modules[$moduleString];
        
        if ($module != null) {
            $moduleHtml = "";
            
            if (! array_key_exists($targetSite, $this->content[$contentString]) && $targetSite == 'index') {
                $moduleHtml = $this->modules[$moduleString]->output('index', $this->content[$contentString], $tmpUrl);
                $values['main'] = $moduleHtml;
                
                foreach ($this->modules[$moduleString]->getFields() as $field) {
                    if ($field != 'content')
                        $values[$field] = '';
                }
            } else {
                // submodules outputs
                $subs = array();
                foreach ($module->getSubviews() as $sub => $subview) {
                    $subs[$sub] = $this->performRecursive($moduleString . $subview, $tmpUrl);
                }
                
                $moduleHtml = $this->modules[$moduleString]->output('view', $this->content[$contentString][$targetSite], $tmpUrl);
       
                // mix subs
                
                $templatesub = \Haanga::compile($moduleHtml);
                $moduleHtml = $templatesub($subs);
                
                $values['main'] = $moduleHtml;
                
                foreach ($this->modules[$moduleString]->getFields() as $field) {
                    if (array_key_exists($field, $this->content[$contentString][$targetSite]) && $field != 'content')
                        $values[$field] = $this->content[$contentString][$targetSite][$field];
                }
            }
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
        $values['meta'] = $meta;
        
        $template = \Haanga::compile(file_get_contents(THEMES . $this->config['system']['theme']['name'] . '/' . $module->getLayout()));
        return $template($values);
    }

    private function performRecursive($pathstring, $url)
    {
        // echo "performRecursive($pathstring, $url)<br/>\n";
        $pathstring = trim($pathstring, '/');
        $html = "";
        
        $moduleString = $this->findModuleInPath(explode('/', $pathstring));
        $targetSite = $this->findSiteInPath($pathstring);
        
        $values = array();
        $module = $this->modules[$moduleString];
        
        $url .= ltrim($moduleString, '');
        
        if ($module != null) {
            $moduleHtml = "NOT FOUND";
            if (! array_key_exists($targetSite, $this->content[$moduleString]) && $targetSite == 'index') {
                
                $moduleHtml = $this->modules[$moduleString]->output('index', $this->content[$moduleString], $url);
                
                foreach ($this->modules[$moduleString]->getFields() as $field) {
                    if ($field != 'content')
                        $values[$field] = '';
                }
            } else {
                
                $moduleHtml = $this->modules[$moduleString]->output('view', $this->content[$moduleString][$targetSite], $url);
                
                foreach ($this->modules[$moduleString]->getFields() as $field) {
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
        $actPath = '';
        $selectedModule = '';
        $i = 1;
        $lastPath = '';
        
        foreach ($path as $file) {
            
            $directThis = false;
            $actPath .= $file;
            $evtlSite = $actPath;
            
            if (array_key_exists('/' . $actPath . '/', $this->content) && $i != sizeof($path)) {
                
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
        if ($pathstring == '/' || $pathstring == '')
            return 'index';
        
        if (strpos($pathstring, '/') > 0) {
            $mod1 = strrchr($pathstring, '/');
            if (substr($mod1, 0, 1) == '/')
                $mod1 = substr($mod1, 1);
            
            if ($mod1 == '')
                $mod1 = 'index';
            
            return $mod1;
        } else
            return $pathstring;
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
