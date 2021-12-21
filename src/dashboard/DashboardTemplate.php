<?php

namespace Raptor\Dashboard;

use codesaur\Template\IndexTemplate;

class DashboardTemplate extends IndexTemplate
{
    function __construct()
    {
        parent::__construct(dirname(__FILE__) . '/dashboard.html');
    }
    
    public function alertNoPermission($alert = null)
    {
        if (empty($alert)) {
            $alert = $this->get('system-no-permission');
        }
        
        $html = '<div class="alert alert-danger shadow-sm fade mt-4 show" role="alert">
                    <i class="bi bi-shield-fill-exclamation" style="margin-right:5px"></i>' . $alert .
                    '<i class="bi bi-arrow-repeat float-right" style="cursor:pointer;font-size:1.2rem;right:10px;top:11px;position:absolute" onclick="window.location.reload();"></i>
                </div>';
                
        $this->render($html);
    }
}
