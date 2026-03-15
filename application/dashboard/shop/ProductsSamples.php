<?php

namespace Dashboard\Shop;

/**
 * Class ProductsSamples
 *
 * Бүтээгдэхүүний жишиг (sample) дата.
 * ProductsModel::__initial() дотроос дуудагдана.
 *
 * @package Dashboard\Shop
 */
class ProductsSamples
{
    /**
     * Жишиг бүтээгдэхүүнүүдийг MN/EN хэл дээр үүсгэх.
     *
     * @param ProductsModel $model
     */
    public static function seed(ProductsModel $model): void
    {
        $path = \dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($path == '\\' || $path == '/' || $path == '.') {
            $path = '';
        }
        $now = \date('Y-m-d H:i:s');
        $seed = [
            'is_active' => 1,
            'published' => 1,
            'created_at' => $now,
            'published_at' => $now,
            'category' => '_raptor_sample_'
        ];

        $model->insert($seed + [
            'code' => 'mn',
            'title' => 'Raptor Framework',
            'photo' => $path . '/assets/images/codesaur_repo.jpg',
            'price' => 0,
            'sku' => 'RPT-FW-001',
            'content' => '<p><a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> нь '
                . 'PHP дээр суурилсан орчин үеийн вэб хөгжүүлэлтийн framework юм.</p>'
                . '<p>PSR стандартуудыг бүрэн дэмждэг, MVC архитектуртай, '
                . 'олон хэлний дэмжлэгтэй контент удирдлагын систем.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'mn',
            'title' => 'Вэб сайт хөгжүүлэлт',
            'price' => 1500000,
            'sku' => 'WEB-DEV-001',
            'content' => '<p>Мэргэжлийн вэб сайт хөгжүүлэлтийн үйлчилгээ. '
                . 'Таны бизнест тохирсон вэб сайтыг захиалгаар хөгжүүлж өгнө.</p>'
                . '<p>Responsive дизайн, SEO оновчлол, контент удирдлагын систем зэрэг '
                . 'бүх шаардлагатай боломжуудыг багтаасан.</p>'
        ]);

        $model->insert($seed + [
            'code' => 'en',
            'title' => 'Raptor Framework',
            'photo' => $path . '/assets/images/codesaur_repo.jpg',
            'price' => 0,
            'sku' => 'RPT-FW-001',
            'content' => '<p><a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> is '
                . 'a modern web development framework built on PHP.</p>'
                . '<p>Fully PSR-compliant, MVC architecture, '
                . 'with multilingual content management system.</p>'
        ]);
        $model->insert($seed + [
            'code' => 'en',
            'title' => 'Web Development',
            'price' => 1500000,
            'sku' => 'WEB-DEV-001',
            'content' => '<p>Professional web development service. '
                . 'We build custom websites tailored to your business needs.</p>'
                . '<p>Responsive design, SEO optimization, content management system '
                . 'and all essential features included.</p>'
        ]);
    }
}
