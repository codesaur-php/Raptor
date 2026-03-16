<?php

namespace Raptor\Content;

/**
 * Class PagesSamples
 *
 * Хуудасны жишиг (sample) дата.
 * PagesModel::__initial() дотроос дуудагдана.
 *
 * @package Raptor\Content
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
        $now = \date('Y-m-d H:i:s');
        $assets = $path . '/assets/images';
        $seed = [
            'published' => 1,
            'created_at' => $now,
            'published_at' => $now,
            'category' => '_raptor_sample_'
        ];

        // ============ MN хуудсууд ============

        // Танилцуулга (root content)
        $model->insert($seed + [
            'code' => 'mn',
            'title' => 'Танилцуулга',
            'position' => 10,
            'content' => '<p>Энэ бол <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> дээр суурилсан демо вэб сайт юм.</p>'
                . '<p>Та энэ хуудсыг <a href="' . $path . '/dashboard">хянах самбар</a>аас засварлах боломжтой.</p>'
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
            'position' => 20
        ]);
        $model->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnAbout['id'],
            'title' => 'Байгууллага',
            'position' => 21,
            'photo' => $assets . '/organization.jpg',
            'content' => '<p>Байгууллагын танилцуулга энд байрлана.</p>'
                . '<p>Энэ хуудсыг <a href="' . $path . '/dashboard">хянах самбар</a>аас засварлах боломжтой.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnAbout['id'],
            'title' => 'Баг',
            'position' => 22,
            'content' => '<p>Манай багийн гишүүдийн танилцуулга.</p>'
                . '<p><img src="' . $assets . '/team.jpg" alt="Баг" class="img-fluid rounded shadow-sm"></p>'
        ]);

        // Динозаврууд (nav -> dropdown menu жишээ)
        $mnDino = $model->insert($seed + [
            'code' => 'mn',
            'title' => 'Динозаврууд',
            'position' => 30
        ]);
        $model->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnDino['id'],
            'title' => 'Велоцираптор',
            'position' => 31,
            'is_featured' => 1,
            'source' => 'Википедиа',
            'photo' => $assets . '/velociraptor.jpg',
            'content' => '<p><strong>Velociraptor</strong> (/vɪˈlɒsɪræptər/) - Латинаар "хурдан баригч" гэсэн утгатай.</p>'
                . '<p>Cretaceous галавын сүүл үе буюу ойролцоогоор 75-71 сая жилийн өмнө амьдарч байсан dromaeosaurid theropod үлэг гүрвэл юм. '
                . '<em>V. mongoliensis</em> зүйлийн олдворуудыг <strong>Монгол</strong> улсаас олсон байдаг.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnDino['id'],
            'title' => 'Тарбозавр',
            'position' => 32,
            'is_featured' => 1,
            'photo' => $assets . '/tarbosaurus.jpg',
            'content' => '<p><strong>Tarbosaurus</strong> - Монголоос олдсон хамгийн алдартай махан идэшт динозавр.</p>'
                . '<p>Tyrannosaurus Rex-ийн хамгийн ойрын төрөл бөгөөд ойролцоогоор 70 сая жилийн өмнө Азид амьдарч байжээ. '
                . 'Монгол палеонтологийн нэн чухал олдвор юм.</p>'
        ]);

        // Бүтээгдэхүүн (link)
        $model->insert($seed + [
            'code' => 'mn',
            'title' => '<i class="bi bi-box2-heart"></i> Бүтээгдэхүүн',
            'position' => 35,
            'link' => $path . '/products'
        ]);

        // Холбоо барих
        $model->insert($seed + [
            'code' => 'mn',
            'title' => 'Холбоо барих',
            'position' => 40,
            'link' => $path . '/contact',
            'content' => '<p>Бидэнтэй холбогдохыг хүсвэл доорх мэдээллийг ашиглана уу.</p>'
                . '<p>Имэйл: info@example.com</p>',
            'is_featured' => 1
        ]);

        // ============ EN хуудсууд ============

        // Introduction (root content)
        $model->insert($seed + [
            'code' => 'en',
            'title' => 'Introduction',
            'position' => 50,
            'content' => '<p>This is a demo website built on the <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a>.</p>'
                . '<p>You can edit this page from the <a href="' . $path . '/dashboard">admin dashboard</a>.</p>'
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
            'position' => 60
        ]);
        $model->insert($seed + [
            'code' => 'en',
            'parent_id' => $enAbout['id'],
            'title' => 'Organization',
            'position' => 61,
            'photo' => $assets . '/organization.jpg',
            'content' => '<p>Organization introduction goes here.</p>'
                . '<p>You can edit this page from the <a href="' . $path . '/dashboard">admin dashboard</a>.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'en',
            'parent_id' => $enAbout['id'],
            'title' => 'Team',
            'position' => 62,
            'content' => '<p>Meet our team members.</p>'
                . '<p><img src="' . $assets . '/team.jpg" alt="Team" class="img-fluid rounded shadow-sm"></p>'
        ]);

        // Dinosaurs (nav -> dropdown menu example)
        $enDino = $model->insert($seed + [
            'code' => 'en',
            'title' => 'Dinosaurs',
            'position' => 70
        ]);
        $model->insert($seed + [
            'code' => 'en',
            'parent_id' => $enDino['id'],
            'title' => 'Velociraptor',
            'position' => 71,
            'is_featured' => 1,
            'source' => 'Wikipedia',
            'photo' => $assets . '/velociraptor.jpg',
            'content' => '<p><strong>Velociraptor</strong> (/vɪˈlɒsɪræptər/) - Latin for "swift seizer".</p>'
                . '<p>A dromaeosaurid theropod dinosaur that lived approximately 75 to 71 million years ago during the Late Cretaceous period. '
                . 'Fossils of <em>V. mongoliensis</em> were discovered in <strong>Mongolia</strong>.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'en',
            'parent_id' => $enDino['id'],
            'title' => 'Tarbosaurus',
            'position' => 72,
            'is_featured' => 1,
            'photo' => $assets . '/tarbosaurus.jpg',
            'content' => '<p><strong>Tarbosaurus</strong> - The most famous carnivorous dinosaur discovered in Mongolia.</p>'
                . '<p>The closest relative of Tyrannosaurus Rex, it lived approximately 70 million years ago in Asia. '
                . 'One of the most important finds in Mongolian paleontology.</p>'
        ]);

        // Products (link)
        $model->insert($seed + [
            'code' => 'en',
            'title' => '<i class="bi bi-box2-heart"></i> Products',
            'position' => 75,
            'link' => $path . '/products'
        ]);

        // Contact
        $model->insert($seed + [
            'code' => 'en',
            'title' => 'Contact',
            'position' => 80,
            'link' => $path . '/contact',
            'content' => '<p>Feel free to reach out to us using the information below.</p>'
                . '<p>Email: info@example.com</p>',
            'is_featured' => 1
        ]);
    }
}
