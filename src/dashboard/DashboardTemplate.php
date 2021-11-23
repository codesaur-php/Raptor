<?php

namespace Raptor\Dashboard;

use codesaur\Template\TwigTemplate;

class DashboardTemplate extends TwigTemplate
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
        if ($this->has('content')) {
            $contents = $this->get('content');
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
        
        if ($this->has('content')) {
            $this->set('content', $this->stringify($this->get('content')));
        }
        
        parent::render();
    }
    
    public function alertNoPermission($alert = null)
    {
        if (empty($alert)) {
            $alert = $this->get('system-no-permission');
        }
        
        $html = '<div class="alert alert-danger shadow-sm fade mt-4 show" role="alert">
                    <i class="bi bi-shield-fill-exclamation" style="margin-right:6px"></i>' . $alert .
                    '<i class="bi bi-arrow-repeat float-right" style="cursor:pointer;font-size:1.2rem;right:10px;top:11px;position:absolute" onclick="window.location.reload();"></i>
                </div>';
                
        $this->render($html);
    }
}
