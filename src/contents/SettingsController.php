<?php

namespace Raptor\Contents;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Mailer\MailerModel;
use Indoraptor\Contents\SettingsModel;

use Raptor\Dashboard\DashboardController;
use Raptor\File\FileController;

class SettingsController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', []);
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
        $context = ['model' => SettingsModel::class];
        if ($this->getRequest()->getMethod() == 'POST') {
            try {
                if (!$this->isUserCan('system_content_settings')) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
                
                $record = [];
                $content = [];
                $payload = $this->getParsedBody();
                foreach ($payload as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                        }
                    } else {
                        $record[$index] = $value;
                    }
                }
                $context['payload'] = $payload;
                
                if (empty($record['alias'])) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                
                if (!empty($record['socials'])) {
                    if (\json_decode($record['socials']) == null) {
                        throw new \Exception('Additional settings for social networks must be valid JSON!', 400);
                    }
                }
                
                if (!empty($record['options'])) {
                    if (\json_decode($record['options']) == null) {
                        throw new \Exception('Extra options must be valid JSON!', 400);
                    }
                }
                
                if (!empty($record['facebook'])) {
                    $fbUrlCheck = '/^(https?:\/\/)?(www\.)?facebook.com\/[a-zA-Z0-9(\.\?)?]/';
                    if (\preg_match($fbUrlCheck, $record['facebook']) != 1) {
                        throw new \Exception('Facebook URL is is not valid!', 400);
                    }
                }
                
                if (!empty($record['twitter'])) {
                    $twUrlCheck = '/^(https?:\/\/)?(www\.)?twitter.com\/[a-zA-Z0-9(\.\?)?]/';
                    if (\preg_match($twUrlCheck, $record['twitter']) != 1) {
                        throw new \Exception('Twitter URL is is not valid!', 400);
                    }
                }
                
                if (!empty($record['youtube'])) {
                    $twUrlCheck = '/^(https?:\/\/)?(www\.)?youtube.com\/[a-zA-Z0-9(\.\?)?]/';
                    if (\preg_match($twUrlCheck, $record['youtube']) != 1) {
                        throw new \Exception('YouTube URL is is not valid!', 400);
                    }
                }
                
                $existing = $this->indosafe('/record?model=' . SettingsModel::class, ['p.alias' => $record['alias'], 'p.is_active' => 1]);
                if (isset($existing['id'])) {
                    $id = $existing['id'];
                    $this->indoput('/record?model=' . SettingsModel::class, ['record' => $record, 'content' => $content, 'condition' => ['WHERE' => "p.id=$id"]]);
                    $notify = 'primary';
                    $notice = $this->text('record-update-success');
                } else {
                    if (empty($content)) {
                        $content[$this->getLanguageCode()]['title'] = '';
                    }
                    $id = $this->indopost('/record?model=' . SettingsModel::class, ['record' => $record, 'content' => $content]);
                    $notify = 'success';
                    $notice = $this->text('record-insert-success');
                }
                $context['record']['id'] = $id;
                
                $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

                $level = LogLevel::INFO;
                $message = 'Системийн тохируулгыг амжилттай хадгаллаа';
            } catch (\Throwable $e) {
                echo $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
                
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
                $record = $this->indoget('/record?model=' . SettingsModel::class, ['p.alias' => $alias, 'p.is_active' => 1]);
            } catch (\Throwable $e) {
                $this->errorLog($e);

                $record = ['alias' => $alias];
            }
            
            $mailer_rows = $this->indosafe('/records?model=' . MailerModel::class);
            if (empty($mailer_rows)) {
                $mailer = ['is_smtp' => 1, 'smtp_auth' => 1];
            } else {
                $mailer = \end($mailer_rows);
            }
            
            $this->twigDashboard(\dirname(__FILE__) . '/settings.html', ['record' => $record, 'mailer' => $mailer])->render();

            $this->indolog('content', LogLevel::NOTICE, 'Системийн тохируулгыг нээж үзэж байна', $context);
        }
    }
    
    public function files()
    {
        try {
            $context = ['model' => SettingsModel::class];
            
            if (!$this->isUserCan('system_content_settings')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $alias = $this->getParsedBody()['alias'] ?? null;
            if (empty($alias)) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            
            $existing = $this->indosafe('/record?model=' . SettingsModel::class, ['p.alias' => $alias, 'p.is_active' => 1]);
            $old_favico_file = \basename($existing['favico'] ?? '');
            $old_shortcut_icon_file = \basename($existing['shortcut_icon'] ?? '');
            $old_apple_touch_icon_file = \basename($existing['apple_touch_icon'] ?? '');
            
            $file = new FileController($this->getRequest());
            $file->setFolder('/settings');
            $file->allowImageOnly();
            
            $record = ['alias' => $alias];
            $content = [];
            foreach (\array_keys($this->getLanguages()) as $code) {
                $old_logo_file = \basename($existing['content']['logo'][$code] ?? '');
                $logo = $file->moveUploaded("logo_$code");
                if ($logo) {
                    $content[$code]['logo'] = $logo['path'];
                }
                if (!empty($old_logo_file)) {
                    if ($file->getLastError() == -1) {
                        $file->tryDeleteFile($old_logo_file);
                        $content[$code]['logo'] = '';
                    } elseif (isset($content[$code]['logo'])
                        && \basename($content[$code]['logo']) != $old_logo_file
                    ) {
                        $file->tryDeleteFile($old_logo_file);
                    }
                }
                if (isset($content[$code]['logo'])) {
                    $context['record']['logo'] = $content[$code]['logo'];
                }
            }

            $file->allowExtensions(['ico']);
            $ico = $file->moveUploaded('favico');
            if ($ico) {
                $record['favico'] = $ico['path'];
            }
            if (!empty($old_favico_file)) {
                if ($file->getLastError() == -1) {
                    $file->tryDeleteFile($old_favico_file);
                    $record['favico'] = '';
                } elseif (isset($record['favico'])
                    && \basename($record['favico']) != $old_favico_file
                ) {
                    $file->tryDeleteFile($old_favico_file);
                }
            }
            if (isset($record['favico'])) {
                $context['record']['favico'] = $record['favico'];
            }
            
            $file->allowImageOnly();
            $shortcut_icon = $file->moveUploaded('shortcut_icon');
            if ($shortcut_icon) {
                $record['shortcut_icon'] = $shortcut_icon['path'];
            }
            if (!empty($old_shortcut_icon_file)) {
                if ($file->getLastError() == -1) {
                    $file->tryDeleteFile($old_shortcut_icon_file);
                    $record['shortcut_icon'] = '';
                } elseif (isset($record['shortcut_icon'])
                    && \basename($record['shortcut_icon']) != $old_shortcut_icon_file
                ) {
                    $file->tryDeleteFile($old_shortcut_icon_file);
                }
            }
            if (isset($record['shortcut_icon'])) {
                $context['record']['shortcut_icon'] = $record['shortcut_icon'];
            }
            
            $apple_touch_icon = $file->moveUploaded('apple_touch_icon');
            if ($apple_touch_icon) {
                $record['apple_touch_icon'] = $apple_touch_icon['path'];
            }
            if (!empty($old_apple_touch_icon_file)) {
                if ($file->getLastError() == -1) {
                    $file->tryDeleteFile($old_apple_touch_icon_file);
                    $record['apple_touch_icon'] = '';
                } elseif (isset($record['apple_touch_icon'])
                    && \basename($record['apple_touch_icon']) != $old_apple_touch_icon_file
                ) {
                    $file->tryDeleteFile($old_apple_touch_icon_file);
                }
            }
            if (isset($record['apple_touch_icon'])) {
                $context['record']['apple_touch_icon'] = $record['apple_touch_icon'];
            }
            
            if (isset($existing['id'])) {
                $id = $existing['id'];
                $this->indoput('/record?model=' . SettingsModel::class, ['record' => $record, 'content' => $content, 'condition' => ['WHERE' => "p.id=$id"]]);
                $notify = 'primary';
                $notice = $this->text('record-update-success');
            } else {
                $id = $this->indopost('/record?model=' . SettingsModel::class, ['record' => $record, 'content' => $content]);
                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }
            $context['record']['id'] = $id;
            $context['content'] = $content;
            
            $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

            $level = LogLevel::INFO;
            $message = 'Системийн тохируулгыг амжилттай хадгаллаа';
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Системийн тохируулгыг хадгалах үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function mailer()
    {
        try {
            $context = ['model' => MailerModel::class, 'reason' => 'mailer'];
            
            if (!$this->isUserCan('system_content_settings')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = [];
            foreach ($this->getParsedBody() as $index => $value) {
                $record[$index] = $value;
            }
            $record['is_smtp'] = isset($record['is_smtp']) && $record['is_smtp'] == 'on' ? 1 : 0;
            $record['smtp_auth'] = isset($record['smtp_auth']) && $record['smtp_auth'] == 'on' ? 1 : 0;
            $context['record'] = $record;
            
            $mailer_rows = $this->indosafe('/records?model=' . MailerModel::class);
            if (!empty($mailer_rows)) {
                $existing = \end($mailer_rows);
            }
            
            if (isset($existing['id'])) {
                $id = $existing['id'];
                $this->indoput('/record?model=' . MailerModel::class, ['record' => $record, 'condition' => ['WHERE' => "id=$id"]]);
                $notify = 'primary';
                $notice = $this->text('record-update-success');
            } else {
                $id = $this->indopost('/record?model=' . MailerModel::class, $record);
                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }
            $context['record']['id'] = $id;
            
            $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

            $level = LogLevel::INFO;
            $message = 'Системийн шууданчын тохируулгыг амжилттай хадгаллаа';
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode(), $e->getCode());

            $level = LogLevel::ERROR;
            $message = 'Системийн шууданчын тохируулгыг хадгалах үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
}
