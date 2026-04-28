<?php

namespace Web\Service;

use Psr\Log\LogLevel;

use Web\Template\TemplateController;

use Raptor\Content\NewsModel;
use Raptor\Content\PagesModel;

use Dashboard\Shop\ProductsModel;

/**
 * Class SeoController
 * ---------------------------------------------------------------
 * SEO контроллер.
 *
 * Энэ контроллер нь:
 *   - HTML Sitemap (sitemap) - хэрэглэгчдэд зориулсан сайтын бүтэц
 *   - XML Sitemap (sitemapXml) - хайлтын системд зориулсан sitemap
 *   - RSS Feed (rss) - мэдээ болон бүтээгдэхүүний RSS feed
 *
 * @package Web\Service
 */
class SeoController extends TemplateController
{
    /**
     * HTML Sitemap хуудсыг харуулах.
     *
     * Хуудсуудыг parent-child бүтэцтэйгээр, мэдээнүүдийг төрлөөр нь
     * бүлэглэж, бүтээгдэхүүнүүдийг жагсааж харуулна.
     *
     * @return void
     */
    public function sitemap()
    {
        $code = $this->getLanguageCode();

        // Хуудсуудыг бүтцээр нь авах (parent -> children)
        $pages_table = (new PagesModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT id, slug, title, parent_id, position FROM $pages_table
             WHERE published=1 AND code=:code
             ORDER BY position, id"
        );
        $all_pages = $stmt->execute([':code' => $code]) ? $stmt->fetchAll() : [];

        // parent_id-аар бүлэглэх
        $children_map = [];
        foreach ($all_pages as $p) {
            $pid = (int)($p['parent_id'] ?? 0);
            $children_map[$pid][] = $p;
        }

        // Recursive tree бүтэц
        $buildTree = function (int $parentId) use (&$buildTree, &$children_map): array {
            $nodes = $children_map[$parentId] ?? [];
            foreach ($nodes as &$node) {
                $node['children'] = $buildTree((int)$node['id']);
            }
            unset($node);
            return $nodes;
        };
        $page_tree = $buildTree(0);

        // Мэдээнүүдийг төрлөөр бүлэглэж авах (тус бүр сүүлийн 10)
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT DISTINCT type FROM $news_table
             WHERE published=1 AND code=:code"
        );
        $news_types = $stmt->execute([':code' => $code]) ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];

        $news_by_type = [];
        foreach ($news_types as $type) {
            // Нийт тоог авах
            $cnt = $this->prepare(
                "SELECT COUNT(*) FROM $news_table
                 WHERE published=1 AND code=:code AND type=:type"
            );
            $cnt->bindValue(':code', $code);
            $cnt->bindValue(':type', $type);
            $cnt->execute();
            $total = (int)$cnt->fetchColumn();

            $stmt = $this->prepare(
                "SELECT title, slug, published_at FROM $news_table
                 WHERE published=1 AND code=:code AND type=:type
                 ORDER BY published_at DESC
                 LIMIT 50"
            );
            $stmt->bindValue(':code', $code);
            $stmt->bindValue(':type', $type);
            $news_by_type[$type] = [
                'items' => $stmt->execute() ? $stmt->fetchAll() : [],
                'total' => $total
            ];
        }

        // Бүтээгдэхүүнүүдийг авах
        $products_table = (new ProductsModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT title, slug, published_at FROM $products_table
             WHERE published=1 AND code=:code
             ORDER BY published_at DESC"
        );
        $products = $stmt->execute([':code' => $code]) ? $stmt->fetchAll() : [];

        $this->webTemplate(__DIR__ . '/sitemap.html', [
            'page_tree' => $page_tree,
            'news_by_type' => $news_by_type,
            'products' => $products,
            'title' => $this->text('sitemap')
        ])->render();

        $this->log('web', LogLevel::NOTICE, '[{server_request.code}] HTML sitemap уншиж байна', ['action' => 'sitemap']);
    }

    /**
     * XML Sitemap үүсгэх (SEO зориулалттай).
     *
     * Бүх хэл дээрх хуудас, мэдээ, бүтээгдэхүүнүүдийг sitemaps.org
     * стандартын дагуу XML форматаар буцаана.
     *
     * @return void
     */
    public function sitemapXml()
    {
        $baseUrl = (string)$this->getRequest()->getUri()->withPath($this->getScriptPath());
        $baseUrl = \rtrim($baseUrl, '/');

        $urls = [];

        // Нүүр хуудас
        $urls[] = [
            'loc' => $baseUrl . '/',
            'changefreq' => 'daily',
            'priority' => '1.0'
        ];

        // Бүх хэл дээрх хуудсууд
        $pages_table = (new PagesModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT slug, updated_at FROM $pages_table
             WHERE published=1
             ORDER BY published_at DESC"
        );
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $urls[] = [
                    'loc' => $baseUrl . '/page/' . $row['slug'],
                    'lastmod' => \date('Y-m-d', \strtotime($row['updated_at'] ?? 'now')),
                    'changefreq' => 'monthly',
                    'priority' => '0.8'
                ];
            }
        }

        // Бүх хэл дээрх мэдээнүүд
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT slug, updated_at FROM $news_table
             WHERE published=1
             ORDER BY published_at DESC"
        );
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $urls[] = [
                    'loc' => $baseUrl . '/news/' . $row['slug'],
                    'lastmod' => \date('Y-m-d', \strtotime($row['updated_at'] ?? 'now')),
                    'changefreq' => 'monthly',
                    'priority' => '0.6'
                ];
            }
        }

        // Бүх хэл дээрх бүтээгдэхүүнүүд
        $products_table = (new ProductsModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT slug, updated_at FROM $products_table
             WHERE published=1
             ORDER BY published_at DESC"
        );
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $urls[] = [
                    'loc' => $baseUrl . '/product/' . $row['slug'],
                    'lastmod' => \date('Y-m-d', \strtotime($row['updated_at'] ?? 'now')),
                    'changefreq' => 'weekly',
                    'priority' => '0.7'
                ];
            }
        }

        // XML output
        if (!\headers_sent()) {
            \header('Content-Type: application/xml; charset=utf-8');
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            echo "  <url>\n";
            echo '    <loc>' . \htmlspecialchars($url['loc']) . "</loc>\n";
            if (isset($url['lastmod'])) {
                echo '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
            }
            echo '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            echo '    <priority>' . $url['priority'] . "</priority>\n";
            echo "  </url>\n";
        }
        echo '</urlset>';
    }

    /**
     * RSS Feed үүсгэх.
     *
     * Сүүлийн 20 мэдээ болон 20 бүтээгдэхүүнийг RSS 2.0 стандартаар
     * буцаана. Atom namespace ашиглана.
     *
     * @return void
     */
    public function rss()
    {
        $code = $this->getLanguageCode();
        $baseUrl = (string)$this->getRequest()->getUri()->withPath($this->getScriptPath());
        $baseUrl = \rtrim($baseUrl, '/');

        // Site settings
        $settings = $this->getAttribute('settings', []);
        $siteTitle = $settings['title'] ?? 'Raptor';
        $siteDescription = $settings['description'] ?? '';

        // Latest 20 news
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT title, slug, description, photo, published_at, 'news' as feed_type
             FROM $news_table
             WHERE published=1 AND code=:code
             ORDER BY published_at DESC
             LIMIT 20"
        );
        $news_items = $stmt->execute([':code' => $code]) ? $stmt->fetchAll() : [];

        // Latest 20 products
        $products_table = (new ProductsModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT title, slug, description, photo, published_at, 'product' as feed_type
             FROM $products_table
             WHERE published=1 AND code=:code
             ORDER BY published_at DESC
             LIMIT 20"
        );
        $product_items = $stmt->execute([':code' => $code]) ? $stmt->fetchAll() : [];

        // Нэгтгэж published_at-аар эрэмбэлэх
        $items = \array_merge($news_items, $product_items);
        \usort($items, function ($a, $b) {
            return \strtotime($b['published_at']) - \strtotime($a['published_at']);
        });

        // XML output
        if (!\headers_sent()) {
            \header('Content-Type: application/rss+xml; charset=utf-8');
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo "<channel>\n";
        echo '  <title>' . \htmlspecialchars($siteTitle) . "</title>\n";
        echo '  <link>' . \htmlspecialchars($baseUrl) . "</link>\n";
        echo '  <description>' . \htmlspecialchars($siteDescription) . "</description>\n";
        echo '  <language>' . \htmlspecialchars($code) . "</language>\n";
        echo '  <lastBuildDate>' . \gmdate('D, d M Y H:i:s') . " GMT</lastBuildDate>\n";
        echo '  <atom:link href="' . \htmlspecialchars($baseUrl . '/rss') . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach ($items as $item) {
            $prefix = ($item['feed_type'] ?? 'news') === 'product' ? '/product/' : '/news/';
            $link = $baseUrl . $prefix . $item['slug'];
            $pubDate = \gmdate('D, d M Y H:i:s', \strtotime($item['published_at'])) . ' GMT';
            $desc = $item['description'] ?? '';

            echo "  <item>\n";
            echo '    <title>' . \htmlspecialchars($item['title']) . "</title>\n";
            echo '    <link>' . \htmlspecialchars($link) . "</link>\n";
            echo '    <guid>' . \htmlspecialchars($link) . "</guid>\n";
            echo '    <pubDate>' . $pubDate . "</pubDate>\n";
            echo '    <description>' . \htmlspecialchars($desc) . "</description>\n";
            if (!empty($item['photo'])) {
                echo '    <enclosure url="' . \htmlspecialchars($item['photo']) . '" type="image/jpeg"/>' . "\n";
            }
            echo "  </item>\n";
        }

        echo "</channel>\n";
        echo '</rss>';
    }
}
