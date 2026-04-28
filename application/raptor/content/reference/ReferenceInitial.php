<?php

namespace Raptor\Content;

/**
 * Class ReferenceInitial
 *
 * Raptor-ийн Content модулийн reference_* хүснэгтүүдэд зориулсан
 * анхны (seed) өгөгдлийг бүртгэдэг тусгай класс.
 *
 * ReferenceModel::__initial() дотор:
 *   if (method_exists(ReferenceInitial::class, $table)) {
 *       ReferenceInitial::$table($this);
 *   }
 * гэж дуудагдана.
 *
 * reference_{name} хүснэгт үүсэх үед энд байгаа
 * public static function reference_{name}(ReferenceModel $model)
 * автоматаар дуудагдаж анхны контентуудыг insert хийнэ.
 */
class ReferenceInitial
{
    /**
     * reference_templates хүснэгтийн анхны (seed) өгөгдлүүд.
     *
     * Бүртгэгдэх контентууд:
     *  - tos                      - Ашиглах нөхцөл (Terms of Service)
     *  - pp                       - Нууцлалын бодлого (Privacy Policy)
     *  - forgotten-password-reset - Нууц үг сэргээх имэйл загвар
     *  - request-new-user         - Шинэ хэрэглэгчийн хүсэлт имэйл
     *  - approve-new-user         - Бүртгэл баталгаажсан имэйл
     *  - dev-request-new          - Шинэ хөгжүүлэлтийн хүсэлт имэйл
     *  - dev-request-response     - Хүсэлтийн хариулт имэйл
     *  - contact-message-notify    - Холбоо барих мессеж админд мэдэгдэх имэйл
     *  - order-status-update      - Захиалгын төлөв шинэчлэгдсэн имэйл
     *  - order-confirmation       - Захиалга баталгаажсан имэйл
     *  - order-notify             - Шинэ захиалга ирсэн тухай админд мэдэгдэх имэйл
     *  - comment-notify           - Шинэ сэтгэгдэл ирсэн тухай админд мэдэгдэх имэйл
     *  - review-notify            - Шинэ үнэлгээ ирсэн тухай админд мэдэгдэх имэйл
     *
     * @param ReferenceModel $model
     */
    public static function reference_templates(ReferenceModel $model)
    {
        // -------------------------------------------------------
        // 1. TOS - Ашиглах нөхцөл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'tos', 'category' => 'system'],
            [
                'mn' => [
                    'title' => 'Веб систем хэрэглэх ерөнхий нөхцөлүүд',
                    'content' =>
                        '<h5>1. Нөхцөл</h5>'
                        . 'Энэхүү вебсайтад нэвтэрснээр та веб системийг хэрэглэх нөхцөл болон дагах хууль журмыг зөвшөөрсөн гэж үзэх бөгөөд '
                        . 'дотоод хуулийг дагаж мөрдөх хариуцлагыг хүлээн зөвшөөрнө. '
                        . 'Хэрэв эдгээр нөхцлийг зөвшөөрөөгүй тохиолдолд вебсайтыг хэрэглэх болон нэвтрэх эрхгүй юм. '
                        . 'Тус вебсайтад агуулагдах материалууд нь худалдааны тэмдгийн хууль болон зохиогчийн эрхийн дагуу хамгаалагдсан болно.'
                        . '<h5>2. Лицензийг хэрэглэх</h5>'
                        . '<ol type="a"><li>Зөвхөн түр хугацаанд арилжааны бус хувийн сонирхлоор харахын тулд '
                        . 'вебсайтад байрлах материалын нэг хувийг урьдчилан татаж авахаар зөвшөөрөл олгогдсон. '
                        . 'Энэ нь лицензийн олголт юм, эрхийн шилжүүлэлт биш бөгөөд энэхүү лицензд хамаарагдахгүй зүйлс:'
                        . '<ul>'
                        . '<li>материалыг өөрчлөх болон хуулбарлах;</li>'
                        . '<li>материалыг ямар нэгэн арилжааны зорилгоор ашиглах, эсвэл олон нийтэд харуулах;</li>'
                        . '<li>вебсайтад байрлах аливаа материалыг хөрвүүлэх эсвэл утга, чиглэлийг нь өөрчлөх;</li>'
                        . '<li>материалаас зохиогчийн эрх болон өмчлөгчийн тамга тэмдэглэгээг арилгах, устгах;</li>'
                        . '<li>материалыг бусдад шилжүүлэх болон бусад сүлжээнд хуулбарлан тавих;</li>'
                        . '</ul></li>'
                        . '<li>Хэрэв эдгээр хоригийг зөрчсөн тохиолдолд лиценз цуцлагдах боломжтой. '
                        . 'Материалыг харах эрхгүй болсон болон лиценз цуцлагдсан үед '
                        . 'татаж авсан бүх електрон болон хэвлэсэн хэлбэрээр байгаа материалуудыг устгах ёстой.</li></ol>'
                        . '<h5>3. Татгалзах</h5>'
                        . '<ol type="a"><li>Bебсайтад байрлаж буй материалууд нь өөрийн байгаа хэлбэрээрээ байршсан болно. '
                        . 'Веб систем нь хязгаарлалтгүй, битүү баталгаа эсвэл энгийн хэрэглээнд нийцэх байдлын нөхцөл, '
                        . 'зохих зорилгын таарамж, эсвэл оюуны өмчийн халдашгүй байдал болон бусад эрхийн зөрчил зэрэг байдлуудад '
                        . 'аливаа баталгаа гаргахгүй болно.</li></ol>'
                        . '<h5>4. Хязгаарлалт</h5>'
                        . 'Веб систем болон түүний нийлүүлэгч нь вебсайтад тулгарсан материалыг хэрэглэх явцад гарсан '
                        . 'аливаа эвдрэл гэмтэлд хариуцлага хүлээхгүй болно.'
                        . '<h5>5. Хэвлэлийн алдаа болон хяналт</h5>'
                        . 'Вебсайтад байрлах материалууд нь техникийн болон хэвлэлийн эсвэл гэрэл зургийн алдаа агуулсан байж болзошгүй юм. '
                        . 'Веб систем нь материалуудыг шинэчлэхэд үүрэг хүлээхгүй болно.'
                        . '<h5>6. Холбоосууд</h5>'
                        . 'Веб систем нь өөрийн вебсайтад байрлах бусад вебсайтуудын холбоосыг хянан шалгаж үзээгүй бөгөөд '
                        . 'уг вебсайтуудад байрлах агууламжид хариуцлага хүлээхгүй болно.'
                        . '<h5>7. Сайтыг хэрэглэх нөхцлийн өөрчлөлт</h5>'
                        . 'Веб систем нь өөрийн вебсайтын хэрэглэх нөхцлийг дахин авч хэлэлцэн мэдэгдэл хийхгүйгээр хэдийд ч өөрчлөх эрхтэй болно.'
                        . '<h5>8. Захирагдах хууль</h5>'
                        . 'Веб систем, вебсайттай холбоотой аливаа зарга нэхэмжлэлийг Монгол Улсын хуулийн дагуу шийдвэрлүүлнэ.'
                ],
                'en' => [
                    'title' => 'Web Site Terms and Conditions of Use',
                    'content' =>
                        '<h5>1. Terms</h5>'
                        . 'By accessing this web site, you are agreeing to be bound by these web site Terms and Conditions of Use, '
                        . 'all applicable laws and regulations, and agree that you are responsible for compliance with any applicable local laws. '
                        . 'If you do not agree with any of these terms, you are prohibited from using or accessing this site. '
                        . 'The materials contained in this web site are protected by applicable copyright and trade mark law.'
                        . '<h5>2. Use License</h5>'
                        . '<ol type="a"><li>Permission is granted to temporarily download one copy of the materials on web site '
                        . 'for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, '
                        . 'and under this license you may not:'
                        . '<ul>'
                        . '<li>modify or copy the materials;</li>'
                        . '<li>use the materials for any commercial purpose, or for any public display;</li>'
                        . '<li>attempt to decompile or reverse engineer any software contained on web site;</li>'
                        . '<li>remove any copyright or other proprietary notations from the materials; or</li>'
                        . '<li>transfer the materials to another person or "mirror" the materials on any other server.</li>'
                        . '</ul></li>'
                        . '<li>This license shall automatically terminate if you violate any of these restrictions '
                        . 'and may be terminated by web system at any time.</li></ol>'
                        . '<h5>3. Disclaimer</h5>'
                        . '<ol type="a"><li>The materials on web site are provided "as is". Web system makes no warranties, expressed or implied, '
                        . 'and hereby disclaims and negates all other warranties.</li></ol>'
                        . '<h5>4. Limitations</h5>'
                        . 'In no event shall web system or its suppliers be liable for any damages arising out of the use '
                        . 'or inability to use the materials on web site.'
                        . '<h5>5. Revisions and Errata</h5>'
                        . 'The materials appearing on web site could include technical, typographical, or photographic errors. '
                        . 'Web system does not warrant that any of the materials on its web site are accurate, complete, or current.'
                        . '<h5>6. Links</h5>'
                        . 'Web system has not reviewed all of the sites linked to its Internet web site and is not responsible for the contents of any such linked site.'
                        . '<h5>7. Site Terms of Use Modifications</h5>'
                        . 'Web system may revise these terms of use for its web site at any time without notice.'
                        . '<h5>8. Governing Law</h5>'
                        . 'Any claim relating to web site shall be governed by the laws of Mongolia without regard to its conflict of law provisions.'
                ]
            ]
        );

        // -------------------------------------------------------
        // 2. PP - Нууцлалын бодлого
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'pp', 'category' => 'system'],
            [
                'mn' => [
                    'title' => 'Хувийн мэдээлэл нууцлалын бодлого',
                    'content' =>
                        'Таны хувийн нууц бол бидэнд маш чухал. '
                        . 'Үүнтэй холбогдуулан бид хувь хүний мэдээллийг хэрхэн цуглуулдаг, хэрэглэдэг, '
                        . 'харилцдаг зэрэг хувийн мэдээллийн хэрэглээг ойлгуулах зорилгын доор энэхүү бодлогыг боловсруулсан юм.'
                        . '<br/><ul>'
                        . '<li>Хувийн мэдээллийг цуглуулахын өмнө эсвэл тухайн үед нь бид ямар зорилгоор уг мэдээллийг цуглуулж байгаа тухай тодорхойлж байх болно.</li>'
                        . '<li>Бид тухайн хувийн мэдээллийг зөвхөн тодорхойлсон зорилгын дагуу ашиглах болно.</li>'
                        . '<li>Бид хувийн мэдээллийг зөвхөн зорилго биелэх хүртэл шаардлагын дагуу хадгалах болно.</li>'
                        . '<li>Бид хувийн мэдээллийг хуулийн дагуу ба үнэн шударгаар цуглуулах болно.</li>'
                        . '<li>Хувийн мэдээллүүд тухайн зорилготой холбоотой, үнэн зөв, бүрэн бүтэн ба сүүлийн үеийн байх шаардлагатай.</li>'
                        . '<li>Бид хувийн мэдээллийг алдагдал болон хулгайлалтаас хамгаалах болно.</li>'
                        . '<li>Хэрэглэгчидээ хувийн мэдээллийн нууцлалын талаарх мэдээллээр хангах болно.</li>'
                        . '</ul>'
                ],
                'en' => [
                    'title' => 'Privacy Policy',
                    'content' =>
                        'Your privacy is very important to us. '
                        . 'Accordingly, we have developed this Policy in order for you to understand how we collect, use, '
                        . 'communicate and disclose and make use of personal information.'
                        . '<br/><ul>'
                        . '<li>Before or at the time of collecting personal information, we will identify the purposes for which information is being collected.</li>'
                        . '<li>We will collect and use of personal information solely with the objective of fulfilling those purposes specified by us.</li>'
                        . '<li>We will only retain personal information as long as necessary for the fulfillment of those purposes.</li>'
                        . '<li>We will collect personal information by lawful and fair means.</li>'
                        . '<li>Personal data should be relevant to the purposes for which it is to be used, and should be accurate, complete, and up-to-date.</li>'
                        . '<li>We will protect personal information by reasonable security safeguards against loss or theft.</li>'
                        . '<li>We will make readily available to customers information about our policies and practices relating to the management of personal information.</li>'
                        . '</ul>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 3. Нууц үг сэргээх имэйл загвар
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'forgotten-password-reset', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Нууг үг дахин тааруулах',
                    'content' =>
                        '<p>Хэн нэгэн (таныг гэж найдаж байна) системийн {{ email }} хаяг бүхий хэрэглэгчийн нууц үгийг шинээр тааруулах хүсэлт илгээсэн байна.</p>'
                        . '<p>Одоогоор таны хэрэглэгчийн тохиргоонд ямар нэгэн өөрчлөлт ороогүй байгаа билээ.</p>'
                        . '<p> </p>'
                        . '<p>Та дараах холбоосыг дарж нууц үгээ шинээр тааруулах боломжтой:</p>'
                        . '<p>{{ link }}</p>'
                        . '<p> </p>'
                        . '<p>Хэрвээ та энэхүү хүсэлтийг илгээгүй бол, бидэнд хариу бичиж энэ тухайгаа мэдэгдэнэ үү.</p>'
                        . '<p>Нууг үгийг солих холбоос зөвхөн {{ minutes }} минутын туршид хүчинтэй байх болно.</p>'
                        . '<p> </p>'
                        . '<p>Хүндэтгэсэн,</p>'
                        . '<p>Хөгжүүлэгчдийн баг.</p>'
                ],
                'en' => [
                    'title' => 'Forgotten password reset',
                    'content' =>
                        '<p>Somebody (hopefully you) requested a new password for user profile for {{ email }}.</p>'
                        . '<p>No changes have been made to your user profile yet.</p>'
                        . '<p> </p>'
                        . '<p>You can reset your password by clicking the link below:</p>'
                        . '<p>{{ link }}</p>'
                        . '<p> </p>'
                        . '<p>If you did no request a new password, please let us know immediately by replying to this email.</p>'
                        . '<p>This password reset is only valid for the next {{ minutes }} minutes.</p>'
                        . '<p> </p>'
                        . '<p>Yours,</p>'
                        . '<p>Support Team</p>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 4. Шинэ хэрэглэгчийн бүртгүүлэх хүсэлт имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'request-new-user', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Хэрэглэгчээр бүртгүүлэх хүсэлт',
                    'content' =>
                        '<p>Сайн байна уу, эрхэм {{ username }}!</p>'
                        . '<p> </p>'
                        . '<p>Та манай систем дээр [{{ username }}] нэр, [{{ email }}] хаяг бүхий шинэ хэрэглэгч бүртгүүлэх хүсэлт илгээсэн байна.</p>'
                        . '<p>Одоогоор уг хүсэлтийн дагуу шинэ хэрэглэгчийг бид системдээ албан ёсоор бүртгээгүй байгаа билээ.</p>'
                        . '<p>Манай систем админ хүсэлтийн дагуу мэдээллийг шалгаж үзээд тохирох үйлдлийг хийх болно.</p>'
                        . '<p>Бидний зүгээс баталгаажсан хариуг дахин илгээх хүртэл та түр хүлээнэ үү.</p>'
                        . '<p> </p>'
                        . '<p>Хэрвээ та энэхүү хүсэлтийг илгээгүй бол, бидэнд хариу бичиж энэ тухайгаа мэдэгдэнэ үү.</p>'
                        . '<p> </p>'
                        . '<p>Хүндэтгэсэн,</p>'
                        . '<p>Хөгжүүлэгчдийн баг.</p>'
                ],
                'en' => [
                    'title' => 'New user registration request',
                    'content' =>
                        '<p>Сайн байна уу, эрхэм {{ username }}!</p>'
                        . '<p> </p>'
                        . '<p>Та манай систем дээр [{{ username }}] нэр, [{{ email }}] хаяг бүхий шинэ хэрэглэгч бүртгүүлэх хүсэлт илгээсэн байна.</p>'
                        . '<p>Одоогоор уг хүсэлтийн дагуу шинэ хэрэглэгчийг бид системдээ албан ёсоор бүртгээгүй байгаа билээ.</p>'
                        . '<p>Манай систем админ хүсэлтийн дагуу мэдээллийг шалгаж үзээд тохирох үйлдлийг хийх болно.</p>'
                        . '<p>Бидний зүгээс баталгаажсан хариуг дахин илгээх хүртэл та түр хүлээнэ үү.</p>'
                        . '<p> </p>'
                        . '<p>Хэрвээ та энэхүү хүсэлтийг илгээгүй бол, бидэнд хариу бичиж энэ тухайгаа мэдэгдэнэ үү.</p>'
                        . '<p> </p>'
                        . '<p>Хүндэтгэсэн,</p>'
                        . '<p>Хөгжүүлэгчдийн баг.</p>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 5. Бүртгэл баталгаажсан имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'approve-new-user', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Хэрэглэгчийн бүртгэл баталгаажлаа',
                    'content' =>
                        '<p>Сайн байна уу, эрхэм {{ username }}?</p>'
                        . '<p>Таньд энэ өдрийн мэнд хүргэе!</p>'
                        . '<p> </p>'
                        . '<p>Таны бүртгэл баталгаажиж хэрэглэгч амжилттай үүслээ.</p>'
                        . '<p>Та {{ login }} хаягаар зочилж, өөрийн бүртгүүлсэн нууц үгээ ашиглан системд нэвтэрнэ үү.</p>'
                        . '<p>Нэвтрэх нэр: <strong>{{ username }}</strong> эсвэл <strong>{{ email }}</strong></p>'
                        . '<p> </p>'
                        . '<p>Хүндэтгэсэн,</p>'
                        . '<p>Хөгжүүлэгчдийн баг.</p>'
                ],
                'en' => [
                    'title' => 'User registration approved',
                    'content' =>
                        '<p>Сайн байна уу, эрхэм {{ username }}?</p>'
                        . '<p>Таньд энэ өдрийн мэнд хүргэе!</p>'
                        . '<p> </p>'
                        . '<p>Таны бүртгэл баталгаажиж хэрэглэгч амжилттай үүслээ.</p>'
                        . '<p>Та {{ login }} хаягаар зочилж, өөрийн бүртгүүлсэн нууц үгээ ашиглан системд нэвтэрнэ үү.</p>'
                        . '<p>Нэвтрэх нэр: <strong>{{ username }}</strong> эсвэл <strong>{{ email }}</strong></p>'
                        . '<p> </p>'
                        . '<p>Хүндэтгэсэн,</p>'
                        . '<p>Хөгжүүлэгчдийн баг.</p>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 6. Шинэ хөгжүүлэлтийн хүсэлт имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'dev-request-new', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Шинэ хөгжүүлэлтийн хүсэлт #{{ request_id }}',
                    'content' =>
                        '<p><strong>{{ author }}</strong> шинэ хөгжүүлэлтийн хүсэлт үүсгэлээ.</p>'
                        . '<p><strong>Гарчиг:</strong> {{ title }}</p>'
                        . '<p> </p>'
                        . '<p><a href="{{ link }}">Хүсэлтийг харах &rarr;</a></p>'
                ],
                'en' => [
                    'title' => 'New Dev Request #{{ request_id }}',
                    'content' =>
                        '<p><strong>{{ author }}</strong> has submitted a new development request.</p>'
                        . '<p><strong>Title:</strong> {{ title }}</p>'
                        . '<p> </p>'
                        . '<p><a href="{{ link }}">View request &rarr;</a></p>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 7. Хүсэлтийн хариулт имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'dev-request-response', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Хариулт: Хүсэлт #{{ request_id }} - {{ title }}',
                    'content' =>
                        '<p><strong>{{ author }}</strong> хариулт бичлээ:</p>'
                        . '<blockquote style="border-left:3px solid #ccc;padding-left:12px;color:#555">{{ response }}</blockquote>'
                        . '<p> </p>'
                        . '<p><a href="{{ link }}">Хүсэлтийг харах &rarr;</a></p>'
                ],
                'en' => [
                    'title' => 'Re: Request #{{ request_id }} - {{ title }}',
                    'content' =>
                        '<p><strong>{{ author }}</strong> has responded:</p>'
                        . '<blockquote style="border-left:3px solid #ccc;padding-left:12px;color:#555">{{ response }}</blockquote>'
                        . '<p> </p>'
                        . '<p><a href="{{ link }}">View request &rarr;</a></p>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 8. Захиалгын төлөв шинэчлэгдсэн имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'order-status-update', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Захиалга #{{ order_id }} - {{ status }}',
                    'content' =>
                        '<p>Сайн байна уу, <strong>{{ customer_name }}</strong>!</p>'
                        . '<p> </p>'
                        . '<p>Таны <strong>#{{ order_id }}</strong> дугаартай захиалгын төлөв шинэчлэгдлээ.</p>'
                        . '<p><strong>Бүтээгдэхүүн:</strong> {{ product_title }}</p>'
                        . '<p><strong>Шинэ төлөв:</strong> {{ status }}</p>'
                        . '<p> </p>'
                        . '<p>Баярлалаа!</p>'
                ],
                'en' => [
                    'title' => 'Order #{{ order_id }} - {{ status }}',
                    'content' =>
                        '<p>Hello, <strong>{{ customer_name }}</strong>!</p>'
                        . '<p> </p>'
                        . '<p>Your order <strong>#{{ order_id }}</strong> status has been updated.</p>'
                        . '<p><strong>Product:</strong> {{ product_title }}</p>'
                        . '<p><strong>New status:</strong> {{ status }}</p>'
                        . '<p> </p>'
                        . '<p>Thank you!</p>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 9. Холбоо барих мессеж админд мэдэгдэх имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'contact-message-notify', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Холбоо барих мессеж - {{ name }}',
                    'content' =>
                        '<div style="font-family:Arial,sans-serif;max-width:600px">'
                        . '<h2 style="color:#333">Холбоо барих мессеж</h2>'
                        . '<table style="width:100%;border-collapse:collapse">'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Нэр</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ name }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Утас</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ phone }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">И-мэйл</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ email }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Мессеж</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ message }}</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:20px;padding:12px;background:#f8f9fa;border-radius:6px;color:#555">'
                            . 'Та энэ мессежийн имэйл хаяг руу шууд хариулж (Reply) болно, '
                            . 'эсвэл дугаар руу нь залгаж утсаар холбогдож болно. '
                            . 'Хариулт өгсний дараа <a href="{{ messages_link }}">удирдлагын самбар - мессежүүд</a> '
                            . 'хэсэгт орж хариулсан гэдгээ тэмдэглэнэ үү.'
                        . '</p>'
                        . '</div>'
                ],
                'en' => [
                    'title' => 'Contact message - {{ name }}',
                    'content' =>
                        '<div style="font-family:Arial,sans-serif;max-width:600px">'
                        . '<h2 style="color:#333">Contact message</h2>'
                        . '<table style="width:100%;border-collapse:collapse">'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Name</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ name }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Phone</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ phone }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Email</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ email }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Message</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ message }}</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:20px;padding:12px;background:#f8f9fa;border-radius:6px;color:#555">'
                            . 'You can reply directly to this email to respond to the visitor, '
                            . 'or call them by phone using the number above. '
                            . 'After responding, please visit <a href="{{ messages_link }}">dashboard - messages</a> '
                            . 'to mark the message as replied.'
                        . '</p>'
                        . '</div>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 10. Захиалга баталгаажсан имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'order-confirmation', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Захиалга баталгаажлаа #{{ order_id }}',
                    'content' =>
                        '<p>Сайн байна уу, <strong>{{ customer_name }}</strong>!</p>'
                        . '<p> </p>'
                        . '<p>Таны захиалга амжилттай бүртгэгдлээ.</p>'
                        . '<p><strong>Захиалгын дугаар:</strong> #{{ order_id }}</p>'
                        . '<p><strong>Бүтээгдэхүүн:</strong> {{ product_title }}</p>'
                        . '<p><strong>Тоо ширхэг:</strong> {{ quantity }}</p>'
                        . '<p> </p>'
                        . '<p>Бид таны захиалгыг хүлээн авсан бөгөөд удахгүй холбогдох болно.</p>'
                        . '<p> </p>'
                        . '<p>Баярлалаа!</p>'
                ],
                'en' => [
                    'title' => 'Order Confirmed #{{ order_id }}',
                    'content' =>
                        '<p>Hello, <strong>{{ customer_name }}</strong>!</p>'
                        . '<p> </p>'
                        . '<p>Your order has been successfully placed.</p>'
                        . '<p><strong>Order number:</strong> #{{ order_id }}</p>'
                        . '<p><strong>Product:</strong> {{ product_title }}</p>'
                        . '<p><strong>Quantity:</strong> {{ quantity }}</p>'
                        . '<p> </p>'
                        . '<p>We have received your order and will contact you shortly.</p>'
                        . '<p> </p>'
                        . '<p>Thank you!</p>'
                ]
            ]
        );
        // -------------------------------------------------------
        // 11. Шинэ захиалга ирсэн тухай админд мэдэгдэх имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'order-notify', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Шинэ захиалга #{{ order_id }} - {{ customer_name }}',
                    'content' =>
                        '<div style="font-family:Arial,sans-serif;max-width:600px">'
                        . '<h2 style="color:#333">Шинэ захиалга</h2>'
                        . '<table style="width:100%;border-collapse:collapse">'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Захиалгын дугаар</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">#{{ order_id }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Захиалагч</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ customer_name }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">И-мэйл</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ customer_email }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Утас</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ customer_phone }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Бүтээгдэхүүн</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ product_title }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Тоо ширхэг</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ quantity }}</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:20px"><a href="{{ orders_link }}">Захиалгууд руу очих &rarr;</a></p>'
                        . '</div>'
                ],
                'en' => [
                    'title' => 'New Order #{{ order_id }} - {{ customer_name }}',
                    'content' =>
                        '<div style="font-family:Arial,sans-serif;max-width:600px">'
                        . '<h2 style="color:#333">New Order</h2>'
                        . '<table style="width:100%;border-collapse:collapse">'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Order ID</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">#{{ order_id }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Customer</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ customer_name }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Email</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ customer_email }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Phone</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ customer_phone }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Product</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ product_title }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Quantity</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ quantity }}</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:20px"><a href="{{ orders_link }}">View orders &rarr;</a></p>'
                        . '</div>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 12. Шинэ сэтгэгдэл ирсэн тухай админд мэдэгдэх имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'comment-notify', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Шинэ сэтгэгдэл - {{ news_title }}',
                    'content' =>
                        '<div style="font-family:Arial,sans-serif;max-width:600px">'
                        . '<h2 style="color:#333">Шинэ сэтгэгдэл</h2>'
                        . '<table style="width:100%;border-collapse:collapse">'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Мэдээ</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ news_title }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Нэр</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ name }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">И-мэйл</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ email }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Сэтгэгдэл</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ comment }}</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:20px"><a href="{{ comments_link }}">Сэтгэгдлүүд руу очих &rarr;</a></p>'
                        . '</div>'
                ],
                'en' => [
                    'title' => 'New Comment - {{ news_title }}',
                    'content' =>
                        '<div style="font-family:Arial,sans-serif;max-width:600px">'
                        . '<h2 style="color:#333">New Comment</h2>'
                        . '<table style="width:100%;border-collapse:collapse">'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">News</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ news_title }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Name</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ name }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Email</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ email }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Comment</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ comment }}</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:20px"><a href="{{ comments_link }}">View comments &rarr;</a></p>'
                        . '</div>'
                ]
            ]
        );

        // -------------------------------------------------------
        // 13. Шинэ үнэлгээ ирсэн тухай админд мэдэгдэх имэйл
        // -------------------------------------------------------
        $model->insert(
            ['keyword' => 'review-notify', 'category' => 'email'],
            [
                'mn' => [
                    'title' => 'Шинэ үнэлгээ - {{ product_title }}',
                    'content' =>
                        '<div style="font-family:Arial,sans-serif;max-width:600px">'
                        . '<h2 style="color:#333">Шинэ үнэлгээ</h2>'
                        . '<table style="width:100%;border-collapse:collapse">'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Бүтээгдэхүүн</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ product_title }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Нэр</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ name }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">И-мэйл</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ email }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Үнэлгээ</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ rating }} / 5</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Сэтгэгдэл</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ comment }}</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:20px"><a href="{{ reviews_link }}">Үнэлгээнүүд руу очих &rarr;</a></p>'
                        . '</div>'
                ],
                'en' => [
                    'title' => 'New Review - {{ product_title }}',
                    'content' =>
                        '<div style="font-family:Arial,sans-serif;max-width:600px">'
                        . '<h2 style="color:#333">New Review</h2>'
                        . '<table style="width:100%;border-collapse:collapse">'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Product</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ product_title }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Name</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ name }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Email</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ email }}</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Rating</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ rating }} / 5</td></tr>'
                        . '<tr><td style="padding:8px;font-weight:bold;border-bottom:1px solid #eee">Comment</td>'
                            . '<td style="padding:8px;border-bottom:1px solid #eee">{{ comment }}</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:20px"><a href="{{ reviews_link }}">View reviews &rarr;</a></p>'
                        . '</div>'
                ]
            ]
        );
    }
}
