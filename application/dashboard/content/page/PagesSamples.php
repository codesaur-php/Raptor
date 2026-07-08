<?php

namespace Dashboard\Content;

/**
 * Class PagesSamples
 *
 * Хуудасны жишиг (sample) дата.
 * PagesModel::__initial() дотроос дуудагдана.
 *
 * @package Dashboard\Content
 */
class PagesSamples
{
    /**
     * Жишиг хуудсуудыг MN/EN хэл дээр үүсгэх.
     *
     * @param PagesModel $model
     */
    public static function seed(PagesModel $model): void
    {
        $path = \dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($path == '\\' || $path == '/' || $path == '.') {
            $path = '';
        }
        $assets = $path . '/assets/images';

        $seed = [
            'category' => '_raptor_sample_',
            'published' => 1,
            'published_at' => \date('Y-m-d H:i:s')
        ];

        // ============ MN хуудсууд ============

        // Танилцуулга (root content)
        $model->insert($seed + [
            'code' => 'mn',
            'title' => 'Танилцуулга',
            'position' => 10,
            'content' => '<p>Энэ бол <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> дээр суурилсан демо вэб сайт юм.</p>'
                . '<p>Та энэ хуудсыг хянах самбараас засварлах боломжтой.</p>'
                . '<p>Холбоосууд:</p>'
                . '<ul>'
                . '<li><a href="https://codesaur.net" target="_blank">codesaur.net</a> - Албан ёсны вэб сайт</li>'
                . '<li><a href="https://github.com/codesaur-php" target="_blank">GitHub</a> - Эх код, бүх package-ууд</li>'
                . '<li><a href="https://packagist.org/packages/codesaur/" target="_blank">Packagist</a> - Composer package-ууд</li>'
                . '</ul>'
        ]);

        // Бидний тухай (nav -> dropdown menu жишээ)
        $mnAbout = $model->insert($seed + [
            'code' => 'mn',
            'title' => 'Бидний тухай',
            'position' => 100
        ]);
        $model->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnAbout['id'],
            'title' => 'Байгууллага',
            'position' => 110,
            'content' => '<p>Байгууллагын танилцуулга энд байрлана.</p>'
                . '<p>Энэ хуудсыг хянах самбараас засварлах боломжтой.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnAbout['id'],
            'title' => 'Баг',
            'position' => 120,
            'content' => '<p>Багийн танилцуулга энд байрлана.</p>'
                . '<p>Энэ хуудсыг хянах самбараас засварлах боломжтой.</p>'
        ]);

        // Мэдээлэл (link)
        $model->insert($seed + [
            'code' => 'mn',
            'title' => '<i class="bi bi-newspaper"></i> Мэдээлэл',
            'position' => 300,
            'link' => $path . '/news/type/all'
        ]);
        
        // Бүтээгдэхүүн (link)
        $model->insert($seed + [
            'code' => 'mn',
            'title' => '<i class="bi bi-box2-heart"></i> Бүтээгдэхүүн',
            'position' => 350,
            'link' => $path . '/products'
        ]);

        // Холбоо барих
        $model->insert($seed + [
            'code' => 'mn',
            'title' => 'Холбоо барих',
            'position' => 400,
            'link' => $path . '/contact',
            'photo' => $assets . '/office-view.jpg',
            'content' => '<p>Таны санал хүсэлт, хамтын ажиллагааны саналыг бид хүлээн авахдаа таатай байна.</p>'
                . '<p>Мессеж илгээн бидэнтэй холбогдоорой.</p>'
                . '<div class="ratio ratio-16x9 mt-4"><iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d87058.28493639703!2d106.84722955!3d47.92123465!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x5d96923d019e1c55%3A0x4c0e232915348968!2sUlaanbaatar%2C%20Mongolia!5e0!3m2!1sen!2s!4v1710700000000" style="border:0" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>',
            'is_featured' => 1
        ]);

        // ============ EN хуудсууд ============

        // Introduction (root content)
        $model->insert($seed + [
            'code' => 'en',
            'title' => 'Introduction',
            'position' => 500,
            'content' => '<p>This is a demo website built on the <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a>.</p>'
                . '<p>You can edit this page from the admin dashboard.</p>'
                . '<p>Links:</p>'
                . '<ul>'
                . '<li><a href="https://codesaur.net" target="_blank">codesaur.net</a> - Official website</li>'
                . '<li><a href="https://github.com/codesaur-php" target="_blank">GitHub</a> - Source code &amp; packages</li>'
                . '<li><a href="https://packagist.org/packages/codesaur/" target="_blank">Packagist</a> - Composer packages</li>'
                . '</ul>'
        ]);

        // About Us (nav -> dropdown menu example)
        $enAbout = $model->insert($seed + [
            'code' => 'en',
            'title' => 'About Us',
            'position' => 600
        ]);
        $model->insert($seed + [
            'code' => 'en',
            'parent_id' => $enAbout['id'],
            'title' => 'Organization',
            'position' => 610,
            'content' => '<p>Organization introduction goes here.</p>'
                . '<p>You can edit this page from the admin dashboard.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'en',
            'parent_id' => $enAbout['id'],
            'title' => 'Team',
            'position' => 620,
            'content' => '<p>Team introduction goes here.</p>'
                . '<p>You can edit this page from the admin dashboard.</p>'
        ]);

        // News (link)
        $model->insert($seed + [
            'code' => 'en',
            'title' => '<i class="bi bi-newspaper"></i> News',
            'position' => 800,
            'link' => $path . '/news/type/all'
        ]);

        // Products (link)
        $model->insert($seed + [
            'code' => 'en',
            'title' => '<i class="bi bi-box2-heart"></i> Products',
            'position' => 850,
            'link' => $path . '/products'
        ]);

        // Contact
        $model->insert($seed + [
            'code' => 'en',
            'title' => 'Contact',
            'position' => 900,
            'link' => $path . '/contact',
            'photo' => $assets . '/office-view.jpg',
            'content' => '<p>We welcome your feedback, inquiries, and partnership proposals.</p>'
                . '<p>Please fill out the form to get in touch with us.</p>'
                . '<div class="ratio ratio-16x9 mt-4"><iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d87058.28493639703!2d106.84722955!3d47.92123465!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x5d96923d019e1c55%3A0x4c0e232915348968!2sUlaanbaatar%2C%20Mongolia!5e0!3m2!1sen!2s!4v1710700000000" style="border:0" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>',
            'is_featured' => 1
        ]);
    }
}
