<?php

namespace Raptor\Localization;

use Exception;

use Psr\Log\LogLevel;

use Indoraptor\Localization\LanguageModel;

use Raptor\Dashboard\DashboardController;

class LanguageController extends DashboardController
{
    public function index()
    {
        $template = $this->twigDashboard($this->text('languages'));
        if (!$this->isUserCan('system_localization_index')) {
            return $template->alertNoPermission();
        }

        $template->render($this->twigTemplate(dirname(__FILE__) . '/languages-index.html'));
        
        $this->indolog('localization', LogLevel::NOTICE, 'Хэлний жагсаалтыг нээж үзэж байна', array('model' => LanguageModel::class));
    }
    
    public function datatable()
    {
        $rows = array();
        
        try {
            if (!$this->isUserCan('system_language_index')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $languages = $this->indo('/record/rows?model=' . LanguageModel::class);
            foreach ($languages as $record) {
                $id = $record['id'];
                $row = array($record['code']);
                
                $row[] = htmlentities($record['full']);
                $row[] = '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.1/images/flags/' . $record['code'] . '.png">';
                $row[] = htmlentities($record['app']);
                $row[] = htmlentities($record['created_at']);

                $action = '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('language-view', array('id' => $id)) . '"><i class="bi bi-eye"></i></a>' . PHP_EOL;
                if ($this->getUser()->can('system_language_update')) {
                    $action .= '<a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                            'href="' . $this->generateLink('language-update', array('id' => $id)) . '"><i class="bi bi-pencil-square"></i></a>' . PHP_EOL;
                }
                if ($this->getUser()->can('system_language_delete')) {
                    $action .= '<a class="delete-language btn btn-sm btn-danger shadow-sm" href="' . $id . '"><i class="bi bi-trash"></i></a>';
                }                
                $row[] = $action;
                
                $rows[] = $row;
            }
        } catch (Exception $e) {
            $this->errorLog($e);
        } finally {
            $this->respondJSON(array(
                'data' => $rows,
                'recordsTotal' => count($rows),
                'recordsFiltered' => count($rows),
                'draw' => (int)($this->getQueryParam('draw') ?? 0)
            ));
        }
    }
}
