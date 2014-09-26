<?php
/**
 * Abstract module for Flupp CMS
 * @package Flupp CMS
 * @author  Christian Klisch
 * @since   0.0.1
 */
namespace framework;

abstract class AbstractModule
{

    protected $name;

    protected $path;

    protected $router;

    protected $functions = array();

    protected $views = array();

    protected $fields = array();

    protected $references = array();

    protected $navigation = array();

    protected $isCached;

    protected $subviews = array();

    protected $parsedown;

    protected $layout;

    protected $pagesPerSite;

    protected $orderBy;

    protected $order;

    /**
     * Constructor
     *
     * @param array $settings
     *            Associative array of settings
     */
    public function __construct($settings = array())
    {
        $this->setSettings($settings);
    }

    public function setSettings($settings = array(), $path = '')
    {
        $this->classname = 'AbstractModule';
        $this->name = 'Abstract Module';
        $this->router = null;
        $this->functions = array();
        $this->views = array(
            'index' => 'index.html',
            'preview' => 'preview.html',
            'view' => 'view.html'
        );
        $this->fields = array(
            'title',
            'meta_description',
            'content'
        );
        $this->references = array();
        $this->navigation = array();
        $this->subviews = array();
        $this->isCached = true;
        $this->layout = 'index.html';
        $this->pagesPerSite = 5;
        $this->orderBy = 'name'; // name, (fields: title, ..)
        $this->order = 'ASC'; // ASC, DESC
                              
        // merge config
        if (array_key_exists('functions', $settings))
            $this->functions = array_merge($this->functions, $settings['functions']);
        if (array_key_exists('views', $settings))
            $this->views = array_merge($this->views, $settings['views']);
        if (array_key_exists('fields', $settings))
            $this->fields = array_merge($this->fields, $settings['fields']);
        if (array_key_exists('references', $settings))
            $this->references = array_merge($this->references, $settings['references']);
        if (array_key_exists('navigation', $settings))
            $this->navigation = array_merge($this->navigation, $settings['navigation']);
        if (array_key_exists('subviews', $settings))
            $this->subviews = array_merge($this->subviews, $settings['subviews']);
        
        if (array_key_exists('classname', $settings))
            $this->classname = $settings['classname'];
        if (array_key_exists('name', $settings))
            $this->name = $settings['name'];
        if (array_key_exists('router', $settings))
            $this->router = $settings['router'];
        if (array_key_exists('isCached', $settings))
            $this->isCached = $settings['isCached'];
        if (array_key_exists('path', $settings))
            $this->path = $settings['path'];
        if (array_key_exists('layout', $settings))
            $this->layout = $settings['layout'];
        if (array_key_exists('pagesPerSite', $settings))
            $this->pagesPerSite = $settings['pagesPerSite'];
        
        if ($path != '') {
            $newViews = array();
            
            foreach ($this->views as $key2 => $view) {
                if (is_file(str_replace('//', '/', MODULES . $path . $view))) {
                    $newViews[$key2] = str_replace('//', '/', MODULES . $path . $view);
                } else 
                    if (is_file(str_replace('//', '/', SYSTEM . 'modules/' . lcfirst(get_class($this)) . '/' . $view))) {
                        $newViews[$key2] = str_replace('//', '/', SYSTEM . 'modules/' . lcfirst(get_class($this)) . '/' . $view);
                    }
            }
            
            $this->views = $newViews;
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function getClassname()
    {
        return $this->classname;
    }

    public function setClassname($classname)
    {
        $this->classname = $classname;
    }

    public function getRouter()
    {
        return $this->router;
    }

    public function setRouter($router)
    {
        $this->router = $router;
    }

    public function getViews()
    {
        return $this->views;
    }

    public function setViews($views)
    {
        $this->views = $views;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    public function getReferences()
    {
        return $this->reference;
    }

    public function setReferences($references)
    {
        $this->references = $references;
    }

    public function getNavigation()
    {
        return $this->navigation;
    }

    public function setNavigation($navigation)
    {
        $this->navigation = $navigation;
    }

    public function getIsCached()
    {
        return $this->isCached;
    }

    public function setIsCached($isCached)
    {
        $this->isCached = $isCached;
    }

    public function getSubviews()
    {
        return $this->subviews;
    }

    public function setSubviews($subviews)
    {
        $this->subviews = $subviews;
    }

    public function getLayout()
    {
        return $this->layout;
    }

    public function setLayout($layout)
    {
        $this->layout = $layout;
    }

    public function getPagesPerSite()
    {
        return $this->pagesPerSite;
    }

    public function setPagesPerSite($pagesPerSite)
    {
        $this->pagesPerSite = $pagesPerSite;
    }

    public function getOrderBy()
    {
        return $this->orderBy;
    }

    public function setOrderBy($orderBy)
    {
        $this->orderBy = $orderBy;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($order)
    {
        $this->order = $order;
    }

    public function output($view, $contents = array(), $targetSite, $url)
    {
        $this->parsedown = new \Parsedown();
        
        $functionname = 'outputview';
        if (array_key_exists($view, $this->views))
            $functionname = 'output' . $view;
        
        return $this->$functionname($contents, $targetSite, $url);
    }

    protected function outputview($contents, $targetSite, $url)
    {
        return $this->defaultoutput('view', $contents, $targetSite, $url);
    }

    protected function outputindex($contents, $targetSite, $url)
    {
        $html = "";
        $preview = array();
        $values = array();
        
        $partContents = $contents;
        
        if ($targetSite == '')
            $targetSite = 0;
        
        if (is_numeric($targetSite)) {
            $orderdContents = $this->sortContent($contents, $this->orderBy, $this->order);
            $partContents = array_slice($orderdContents, $targetSite * $this->pagesPerSite, $this->pagesPerSite);
            
            $values['pagination_previous_page'] = '';
            $values['pagination_next_page'] = '';
            
            if ($targetSite > 0)
                $values['pagination_previous_page'] = $targetSite - 1;
            
            if ($targetSite * $this->pagesPerSite + 5 < sizeof($contents))
                $values['pagination_next_page'] = $targetSite + 1;
        }
        
        foreach ($partContents as $urlk => $content) {
            $contents[$urlk]['url'] = $url . $urlk;
            $preview[] = $this->outputpreview($contents, $urlk, $url);
        }
        
        $values['previews'] = $preview;
        
        $template = \Haanga::compile(file_get_contents($this->views['index']));
        return $template($values);
    }

    protected function outputpreview($contents, $targetSite, $url)
    {
        return $this->defaultoutput('preview', $contents, $targetSite, $url);
    }

    protected function defaultoutput($view, $contents, $targetSite, $url)
    {
        $rawContent = $contents[$targetSite]['content'];
        $htmlContent = $this->parsedown->text($rawContent);
        
        $values = array(
            'content' => $htmlContent
        );
        foreach ($this->fields as $field) {
            if (array_key_exists($field, $contents[$targetSite]) && $field != 'content')
                $values[$field] = $contents[$targetSite][$field];
        }
        
        if (array_key_exists('url', $contents[$targetSite]))
            $values['url'] = $contents[$targetSite]['url'];
        
        $template = \Haanga::compile(file_get_contents($this->views[$view]));
        return $template($values);
    }

    protected function sortContent($contents, $orderBy, $order)
    {
        $returnarray = $contents;
        
        if ($orderBy == 'name') {
            if ($order == 'ASC')
                ksort($returnarray);
            else 
                if ($order == 'DESC')
                    krsort($returnarray);
        } else {
            
            $orderData = array();
            foreach ($returnarray as $key => $row) {
                if (! array_key_exists($orderBy, $row))
                    break;
                $orderData[$key] = $row[$orderBy];
            }
            
            if ($order == 'ASC')
                array_multisort($orderData, SORT_ASC, $returnarray);
            else 
                if ($order == 'DESC')
                    array_multisort($orderData, SORT_DESC, $returnarray);
        }
        
        return $returnarray;
    }
}

?>