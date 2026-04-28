<?php

namespace Raptor\Content;

/**
 * Class NewsSamples
 *
 * Мэдээний жишиг (sample) дата.
 * NewsModel::__initial() дотроос дуудагдана.
 *
 * @package Raptor\Content
 */
class NewsSamples
{
    /**
     * Жишиг мэдээнүүдийг MN/EN хэл дээр үүсгэх.
     *
     * @param NewsModel $model
     */
    public static function seed(NewsModel $model): void
    {
        $path = \dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($path == '\\' || $path == '/' || $path == '.') {
            $path = '';
        }
        
        $seed = [
            'category' => '_raptor_sample_',
            'published' => 1,
            'published_at' => \date('Y-m-d H:i:s')
        ];

        // MN мэдээнүүд
        $model->insert($seed + [
            'code' => 'mn',
            'type' => 'technology',
            'title' => 'Raptor Framework суулгагдлаа',
            'source' => 'codesaur.net, packagist.org, github.com',
            'photo' => $path . '/assets/images/code.jpg',
            'content' => '<p><a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> амжилттай суулгагдлаа. '
                . 'Та одоо <a href="' . $path . '/dashboard">хянах самбар</a>аас контент удирдах боломжтой.</p>'
                . '<p>Ашиглагдаж буй codesaur package-ууд:</p>'
                . '<ul>'
                . '<li><a href="https://github.com/codesaur-php/http-application" target="_blank">codesaur/http-application</a> - PSR-7/15 HTTP Application, Middleware</li>'
                . '<li><a href="https://github.com/codesaur-php/router" target="_blank">codesaur/router</a> - URL Router</li>'
                . '<li><a href="https://github.com/codesaur-php/http-message" target="_blank">codesaur/http-message</a> - PSR-7 HTTP Message</li>'
                . '<li><a href="https://github.com/codesaur-php/dataobject" target="_blank">codesaur/dataobject</a> - PDO суурьтай ORM (Model, LocalizedModel)</li>'
                . '<li><a href="https://github.com/codesaur-php/template" target="_blank">codesaur/template</a> - Template Engine</li>'
                . '<li><a href="https://github.com/codesaur-php/http-client" target="_blank">codesaur/http-client</a> - HTTP Client (OpenAI API)</li>'
                . '<li><a href="https://github.com/codesaur-php/container" target="_blank">codesaur/container</a> - PSR-11 DI Container</li>'
                . '</ul>'
        ]);
        $model->insert($seed + [
            'code' => 'mn',
            'type' => 'announcement',
            'title' => 'Тавтай морилно уу',
            'photo' => $path . '/assets/images/welcome.jpg',
            'content' => '<p>Манай вэб сайтад тавтай морилно уу. '
                . 'Энэ бол <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor</a> дээр суурилсан демо сайт юм.</p>'
                . '<p>Дэлгэрэнгүй мэдээллийг <a href="https://codesaur.net" target="_blank">codesaur.net</a> '
                . 'болон <a href="https://packagist.org/packages/codesaur/" target="_blank">Packagist</a> дээрээс авна уу.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'mn',
            'type' => 'guide',
            'title' => 'CMS систем ашиглах заавар',
            'content' => '<p><a href="' . $path . '/dashboard">Хянах самбар</a>т нэвтэрч мэдээ болон хуудсуудыг удирдаарай. '
                . 'Анхдагч нэвтрэх: admin / password</p>'
        ]);

        // EN мэдээнүүд
        $model->insert($seed + [
            'code' => 'en',
            'type' => 'technology',
            'title' => 'Raptor Framework Installed',
            'source' => 'codesaur.net, packagist.org, github.com',
            'photo' => $path . '/assets/images/code.jpg',
            'content' => '<p><a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> has been successfully installed. '
                . 'You can now manage content from the <a href="' . $path . '/dashboard">admin dashboard</a>.</p>'
                . '<p>codesaur packages in use:</p>'
                . '<ul>'
                . '<li><a href="https://github.com/codesaur-php/http-application" target="_blank">codesaur/http-application</a> - PSR-7/15 HTTP Application, Middleware</li>'
                . '<li><a href="https://github.com/codesaur-php/router" target="_blank">codesaur/router</a> - URL Router</li>'
                . '<li><a href="https://github.com/codesaur-php/http-message" target="_blank">codesaur/http-message</a> - PSR-7 HTTP Message</li>'
                . '<li><a href="https://github.com/codesaur-php/dataobject" target="_blank">codesaur/dataobject</a> - PDO-based ORM (Model, LocalizedModel)</li>'
                . '<li><a href="https://github.com/codesaur-php/template" target="_blank">codesaur/template</a> - Template Engine</li>'
                . '<li><a href="https://github.com/codesaur-php/http-client" target="_blank">codesaur/http-client</a> - HTTP Client (OpenAI API)</li>'
                . '<li><a href="https://github.com/codesaur-php/container" target="_blank">codesaur/container</a> - PSR-11 DI Container</li>'
                . '</ul>'
        ]);
        $model->insert($seed + [
            'code' => 'en',
            'type' => 'announcement',
            'title' => 'Welcome to Our Website',
            'photo' => $path . '/assets/images/welcome.jpg',
            'content' => '<p>Welcome to our website. '
                . 'This is a demo site built on <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor</a>.</p>'
                . '<p>For more information visit <a href="https://codesaur.net" target="_blank">codesaur.net</a> '
                . 'and <a href="https://packagist.org/packages/codesaur/" target="_blank">Packagist</a>.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'en',
            'type' => 'guide',
            'title' => 'Getting Started with CMS',
            'content' => '<p>Log in to the <a href="' . $path . '/dashboard">admin dashboard</a> to manage news and pages. '
                . 'Default credentials: admin / password</p>'
        ]);
    }
}
