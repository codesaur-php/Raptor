<?php

namespace Raptor\Contents;

use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Record\SettingsModel;
use Indoraptor\Mailer\MailerModel;

use Raptor\Dashboard\DashboardController;
use Raptor\File\FileController;

class SettingsController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', array());
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])
            && isset($localization['text']['settings'])
        ) {
            $meta['content']['title'][$localization['code']] = $localization['text']['settings'];
            $request = $request->withAttribute('meta', $meta);
        }
        
        parent::__construct($request);
    }
    
    public function index()
    {
        $context = array('model' => SettingsModel::class);
        if ($this->getRequest()->getMethod() == 'POST') {
            try {
                if (!$this->isUserCan('system_content_settings')) {
                    throw new Exception($this->text('system-no-permission'), 401);
                }
                
                $record = array();
                $content = array();
                foreach ($this->getParsedBody() as $index => $value) {
                    if (is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                        }
                    } else {
                        $record[$index] = $value;
                    }
                }
                $context['record'] = $record;
                $context['content'] = $content;
                
                if (empty($record['alias'])) {
                    throw new Exception($this->text('invalid-request'), 400);
                }
                
                if (!empty($record['socials'])) {
                    if (json_decode($record['socials']) === null) {
                        throw new Exception('Additional settings for social networks must be valid JSON!', 400);
                    }
                }
                
                if (!empty($record['options'])) {
                    if (json_decode($record['options']) === null) {
                        throw new Exception('Extra options must be valid JSON!', 400);
                    }
                }
                
                if (!empty($record['facebook'])) {
                    $fbUrlCheck = '/^(https?:\/\/)?(www\.)?facebook.com\/[a-zA-Z0-9(\.\?)?]/';
                    if (preg_match($fbUrlCheck, $record['facebook']) != 1) {
                        throw new Exception('Facebook URL is is not valid!', 400);
                    }
                }
                
                if (!empty($record['twitter'])) {
                    $twUrlCheck = '/^(https?:\/\/)?(www\.)?twitter.com\/[a-zA-Z0-9(\.\?)?]/';
                    if (preg_match($twUrlCheck, $record['twitter']) != 1) {
                        throw new Exception('Twitter URL is is not valid!', 400);
                    }
                }
                
                if (!empty($record['youtube'])) {
                    $twUrlCheck = '/^(https?:\/\/)?(www\.)?youtube.com\/[a-zA-Z0-9(\.\?)?]/';
                    if (preg_match($twUrlCheck, $record['youtube']) != 1) {
                        throw new Exception('YouTube URL is is not valid!', 400);
                    }
                }
                
                $pattern = '/record?model=' . SettingsModel::class;
                
                $existing = $this->indosafe($pattern, array('p.alias' => $record['alias'], 'p.is_active' => 1));                
                if (isset($existing['id'])) {
                    $id = $existing['id'];
                    $this->indoput($pattern, array('record' => $record, 'content' => $content, 'condition' => array('WHERE' => "id=$id")));
                    $notify = 'primary';
                    $notice = $this->text('record-update-success');
                } else {
                    if (empty($content)) {
                        $content[$this->getLanguageCode()]['title'] = '';
                    }
                    $id = $this->indopost($pattern, array('record' => $record, 'content' => $content));
                    $notify = 'success';
                    $notice = $this->text('record-insert-success');
                }
                $context['record']['id'] = $id;
                
                $this->respondJSON(array('status' => 'success', 'type' => $notify, 'message' => $notice));

                $level = LogLevel::INFO;
                $message = 'Системийн тохируулгыг амжилттай хадгаллаа';
            } catch (Throwable $e) {
                echo $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());                

                $level = LogLevel::ERROR;
                $message = 'Системийн тохируулгыг хадгалах үед алдаа гарч зогслоо';
            } finally {
                $this->indolog('contents', $level, $message, $context);
            }
        } else {
            if (!$this->isUserCan('system_content_settings')) {
                $this->dashboardProhibited(null, 401)->render();
                return;
            }

            $alias = $this->getUser()->getOrganization()['alias'];

            try {
                $record = $this->indo('/record?model=' . SettingsModel::class, array('alias' => $alias, 'is_active' => 1));
            } catch (Throwable $e) {
                $this->errorLog($e);

                $record = array('alias' => $alias);
            }
            
            $mailer_rows = $this->indosafe('/record/rows?model=' . MailerModel::class);
            if (empty($mailer_rows)) {
                $mailer = array('is_smtp' => 1, 'smtp_auth' => 1);
            } else {
                $mailer = end($mailer_rows);
            }
            
            $this->twigDashboard(dirname(__FILE__) . '/settings.html', array('record' => $record, 'mailer' => $mailer))->render();

            $this->indolog('contents', LogLevel::NOTICE, 'Системийн тохируулгыг нээж үзэж байна', $context);
        }
    }
    
    public function files()
    {
        $context = array('model' => SettingsModel::class);
        
        try {
            if (!$this->isUserCan('system_content_settings')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $alias = $this->getParsedBody()['alias'] ?? null;
            if (empty($alias)) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            
            $pattern = '/record?model=' . SettingsModel::class;
            
            $existing = $this->indosafe($pattern, array('p.alias' => $alias, 'p.is_active' => 1));
            $old_favico_file = basename($existing['favico'] ?? '');
            $old_shortcut_icon_file = basename($existing['shortcut_icon'] ?? '');
            $old_apple_touch_icon_file = basename($existing['apple_touch_icon'] ?? '');
            
            $file = new FileController($this->getRequest());
            $file->init("/settings");
            $file->allowType(3);
            
            $record = array('alias' => $alias);
            $content = array();
            foreach (array_keys($this->getLanguages()) as $code) {
                $old_logo_file = basename($existing['content']['logo'][$code] ?? '');
                $logo = $file->moveUploaded("logo_$code");
                if (isset($logo['name'])) {
                    $content[$code]['logo'] = $file->getPathUrl($logo['name']);
                }
                if (!empty($old_logo_file)) {
                    if ($file->getLastError() == -1) {
                        $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/settings/$old_logo_file");
                        $content[$code]['logo'] = '';
                    } else if (isset($logo['name']) && $logo['name'] != $old_logo_file) {
                        $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/settings/$old_logo_file");
                    }
                }                
                if (isset($content[$code]['logo'])) {
                    $context['record']['logo'] = $content[$code]['logo'];
                }
            }

            $file->allowExtensions(['ico']);
            $ico = $file->moveUploaded('favico');
            if (isset($ico['name'])) {
                $record['favico'] = $file->getPathUrl($ico['name']);
            }                
            if (!empty($old_favico_file)) {
                if ($file->getLastError() == -1) {
                    $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/settings/$old_favico_file");
                    $record['favico'] = '';
                } else if (isset($ico['name']) && $ico['name'] != $old_favico_file) {
                    $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/settings/$old_favico_file");
                }
            }
            if (isset($record['favico'])) {
                $context['record']['favico'] = $record['favico'];
            }
            
            $file->allowType(3);
            $shortcut_icon = $file->moveUploaded('shortcut_icon');
            if (isset($shortcut_icon['name'])) {
                $record['shortcut_icon'] = $file->getPathUrl($shortcut_icon['name']);
            }
            if (!empty($old_shortcut_icon_file)) {
                if ($file->getLastError() == -1) {
                    $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/settings/$old_shortcut_icon_file");
                    $record['shortcut_icon'] = '';
                } else if (isset($shortcut_icon['name']) && $shortcut_icon['name'] != $old_shortcut_icon_file) {
                    $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/settings/$old_shortcut_icon_file");
                }
            }
            if (isset($record['shortcut_icon'])) {
                $context['record']['shortcut_icon'] = $record['shortcut_icon'];
            }
            
            $file->allowType(3);
            $apple_touch_icon = $file->moveUploaded('apple_touch_icon');
            if (isset($apple_touch_icon['name'])) {
                $record['apple_touch_icon'] = $file->getPathUrl($apple_touch_icon['name']);
            }
            if (!empty($old_apple_touch_icon_file)) {
                if ($file->getLastError() == -1) {
                    $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/settings/$old_apple_touch_icon_file");
                    $record['apple_touch_icon'] = '';
                } else if (isset($apple_touch_icon['name']) && $apple_touch_icon['name'] != $old_apple_touch_icon_file) {
                    $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/settings/$old_apple_touch_icon_file");
                }
            }
            if (isset($record['apple_touch_icon'])) {
                $context['record']['apple_touch_icon'] = $record['apple_touch_icon'];
            }
            
            if (isset($existing['id'])) {
                $id = $existing['id'];
                $this->indoput($pattern, array('record' => $record, 'content' => $content, 'condition' => array('WHERE' => "id=$id")));
                $notify = 'primary';
                $notice = $this->text('record-update-success');
            } else {
                $id = $this->indopost($pattern, array('record' => $record, 'content' => $content));
                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }
            $context['record']['id'] = $id;
            $context['content'] = $content;
            
            $this->respondJSON(array('status' => 'success', 'type' => $notify, 'message' => $notice));

            $level = LogLevel::INFO;
            $message = 'Системийн тохируулгыг амжилттай хадгаллаа';
        } catch (Throwable $e) {
            $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());                

            $level = LogLevel::ERROR;
            $message = 'Системийн тохируулгыг хадгалах үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('contents', $level, $message, $context);
        }
    }
    
    public function mailer()
    {
        $context = array('model' => MailerModel::class, 'reason' => 'mailer');
        
        try {
            if (!$this->isUserCan('system_content_settings')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $record = array();
            foreach ($this->getParsedBody() as $index => $value) {
                $record[$index] = $value;
            }
            $record['is_smtp'] = isset($record['is_smtp']) && $record['is_smtp'] == 'on' ? 1 : 0;
            $record['smtp_auth'] = isset($record['smtp_auth']) && $record['smtp_auth'] == 'on' ? 1 : 0;            
            $context['record'] = $record;
            
            $mailer_rows = $this->indosafe('/record/rows?model=' . MailerModel::class);
            if (!empty($mailer_rows)) {
                $existing = end($mailer_rows);
            }
            
            $pattern = '/record?model=' . MailerModel::class;
            if (isset($existing['id'])) {
                $id = $existing['id'];
                $this->indoput($pattern, array('record' => $record, 'condition' => array('WHERE' => "id=$id")));
                $notify = 'primary';
                $notice = $this->text('record-update-success');
            } else {
                $id = $this->indopost($pattern, array('record' => $record));
                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }
            $context['record']['id'] = $id;
            
            $this->respondJSON(array('status' => 'success', 'type' => $notify, 'message' => $notice));

            $level = LogLevel::INFO;
            $message = 'Системийн шууданчын тохируулгыг амжилттай хадгаллаа';
        } catch (Throwable $e) {
            $this->respondJSON(array('message' => $e->getMessage()), $e->getCode(), $e->getCode());

            $level = LogLevel::ERROR;
            $message = 'Системийн шууданчын тохируулгыг хадгалах үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('contents', $level, $message, $context);
        }
    }
}
