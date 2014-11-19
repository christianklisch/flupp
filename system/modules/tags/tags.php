<?php

/**
 * Tags module with default implementation
 * @package Flupp CMS
 * @author  Christian Klisch
 * @since   0.0.1
 */
class Tags extends \framework\AbstractModule
{

    public function output($view, $contents = array(), $targetSite, $url, $values, $allContents = array(), $systemUrl = '')
    {
        if ($values == null)
            $values = array();
        $this->parsedown = new \Parsedown();
        
        $alltags = array();
        $allsites = array();
        
        foreach ($allContents as $urlk => $content) {
            foreach ($content as $site => $meta) {
                if (array_key_exists('tags', $meta)) {
                    $tagsArray = $meta['tags'];
                    $tags = explode(' ', $tagsArray);
                    $alltags = array_merge($alltags, $tags);
                    
                    foreach ($tags as $tag)
                        if ($tag == $targetSite)
                            $allsites[$meta['title']] = $systemUrl . $urlk . $site;
                }
            }
        }
        
        $contents = array();
        foreach ($alltags as $title) {
            $values['title'] = $title;
            $contents[$title]['title'] = $title;
            $contents[$title]['content'] = '';
        }
        
        if ($targetSite == 'index')
            $functionname = 'outputindex';
        else 
            if (array_key_exists($view, $this->views)) {
                $functionname = 'output' . $view;
                $values['title'] = $targetSite;
            }
        
        $values['tags'] = $alltags;
        $values['sites'] = $allsites;
        return $this->$functionname($contents, $targetSite, $url, $values);
    }
}