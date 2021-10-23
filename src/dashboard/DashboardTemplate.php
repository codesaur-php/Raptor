<?php

namespace Raptor\Dashboard;

use codesaur\Template\TwigTemplate;

abstract class DashboardTemplate extends TwigTemplate
{
    function __construct(array $vars = null)
    {
        parent::__construct(dirname(__FILE__) . '/dashboard.html', $vars);
                
        $this->set('meta', array());
    }
    
    public function title($value)
    {
        if (!empty($value)) {
            $this->get('meta')['title'] = $value;
        }
        
        return $this;
    }

    public function addContent($content)
    {
        $contents = $this->get('content');
        if (isset($contents)) {
            if (!is_array($contents)) {
                $contents = array($contents);
            }
        } else {
            $contents = array();
        }
        $contents[] = $content;
        $this->set('content', $contents);
    }
    
    public function render($content = null)
    {
        if (!empty($content)) {
            $this->addContent($content);
        }
        
        $this->set('content', $this->stringify($this->get('content')));
        
        parent::render();
    }
}
