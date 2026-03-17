<?php

namespace Raptor\Localization;

/**
 * Class TextInitial
 *
 * Raptor-ийн нутагшуулалтын (localization) системд ашиглагдах
 * урьдчилсан (seed) текстүүдийг тодорхойлдог тусгай класс юм.
 *
 * Бүх орчуулгын текстүүд нэг хүснэгтэд (single-table architecture)
 * хадгалагддаг бөгөөд localization_text() функц нь системийн бүх
 * seed текстүүдийг нэг дор суулгана.
 *
 * LocalizationController энэ класс дахь seed функцийг шалгаж:
 *
 *   * орчуулгын хүснэгт системд байх ёстой эсэх,
 *   * эхний seed текстүүд байгаа эсэх,
 *   * орчуулгын боломж нээлттэй эсэх
 *
 * гэдгийг автоматаар тодорхойлдог.
 *
 * Хэрэв орчуулгын хүснэгтэд эхний өгөгдөл (seed data) шаардлагатай бол
 * функц дотор:
 *      $model->insert([...], [...]);
 * гэж дуудахад хангалттай. TextModel автоматаар олон хэл дээр хадгална.
 *
 * Энэ класс нь Raptor-ийн localization subsystem-ийн цөм хэсэг бөгөөд
 * орчуулгын модулиудыг нэг дор төвлөрүүлэн удирдах, илрүүлэх, болон seed хийх
 * үндсэн зориулалттай.
 */
class TextInitial
{
    /**
     * localization_text()
     *
     * Системийн бүх локализацийн текстүүдийг нэг функцээр суулгана.
     * Бүх түлхүүрүүд цагаан толгойн дарааллаар эрэмбэлэгдсэн.
     * Хоёр хэлээр: mn, en
     */
    public static function localization_text(TextModel $model)
    {
        $model->insert(['keyword' => 'accept', 'type' => 'sys-defined'], ['mn' => ['text' => 'Зөвшөөрөх'], 'en' => ['text' => 'Accept']]);
        $model->insert(['keyword' => 'action', 'type' => 'sys-defined'], ['mn' => ['text' => 'Үйлдэл'], 'en' => ['text' => 'Action']]);

        $model->insert(['keyword' => 'active-user-can-login', 'type' => 'sys-defined'], ['mn' => ['text' => 'зөвхөн идэвхитэй хэрэглэгч системд нэвтэрч чадна'], 'en' => ['text' => 'only active users can login']]);

        $model->insert(['keyword' => 'add-record', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бичлэг нэмэх'], 'en' => ['text' => 'Add Record']]);
        $model->insert(['keyword' => 'additional-info', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нэмэлт мэдээлэл'], 'en' => ['text' => 'Additional Information']]);

        $model->insert(['keyword' => 'address', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хаяг'], 'en' => ['text' => 'Address']]);
        $model->insert(['keyword' => 'allow-write', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бичих зөвшөөрөх'], 'en' => ['text' => 'Allow write']]);
        $model->insert(['keyword' => 'archive', 'type' => 'sys-defined'], ['mn' => ['text' => 'Архив'], 'en' => ['text' => 'Archive']]);
        $model->insert(['keyword' => 'ask-dont-have-user-yet', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэрэглэгч болж амжаагүй байна уу?'], 'en' => ['text' => 'Don\'t have an user yet?']]);
        $model->insert(['keyword' => 'assign-to', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хариуцуулах'], 'en' => ['text' => 'Assign to']]);
        $model->insert(['keyword' => 'assigned-to', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хариуцагч'], 'en' => ['text' => 'Assigned to']]);
        $model->insert(['keyword' => 'attachments', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хавсралт файлууд'], 'en' => ['text' => 'Attachments']]);
        $model->insert(['keyword' => 'author', 'type' => 'sys-defined'], ['mn' => ['text' => 'Зохиогч'], 'en' => ['text' => 'Author']]);
        $model->insert(['keyword' => 'auto-generate-from-content', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хоосон үлдээвэл агуулгаас автоматаар үүсгэнэ'], 'en' => ['text' => 'Leave empty to auto-generate from content']]);
        $model->insert(['keyword' => 'back', 'type' => 'sys-defined'], ['mn' => ['text' => 'Буцах'], 'en' => ['text' => 'Back']]);
        $model->insert(['keyword' => 'back-to-home', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нүүр хуудас руу буцах'], 'en' => ['text' => 'Back to Home']]);
        $model->insert(['keyword' => 'barcode', 'type' => 'sys-defined'], ['mn' => ['text' => 'Баркод'], 'en' => ['text' => 'Barcode']]);
        $model->insert(['keyword' => 'can-comment', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэрэглэгч сэтгэгдэл үлдээж(бичиж) болох эсэх'], 'en' => ['text' => 'Can user comment on this post?']]);
        $model->insert(['keyword' => 'cancel', 'type' => 'sys-defined'], ['mn' => ['text' => 'Болих'], 'en' => ['text' => 'Cancel']]);
        $model->insert(['keyword' => 'cannot-set-descendant-as-parent', 'type' => 'sys-defined'], ['mn' => ['text' => 'Өөрийн дэд хуудсыг эцэг хуудсаар сонгох боломжгүй'], 'en' => ['text' => 'Cannot set a descendant page as parent']]);
        $model->insert(['keyword' => 'category', 'type' => 'sys-defined'], ['mn' => ['text' => 'Ангилал'], 'en' => ['text' => 'Category']]);
        $model->insert(['keyword' => 'change', 'type' => 'sys-defined'], ['mn' => ['text' => 'Өөрчлөх'], 'en' => ['text' => 'Change']]);
        $model->insert(['keyword' => 'choose', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сонгох'], 'en' => ['text' => 'Choose']]);
        $model->insert(['keyword' => 'clear', 'type' => 'sys-defined'], ['mn' => ['text' => 'Цэвэрлэх'], 'en' => ['text' => 'Clear']]);
        $model->insert(['keyword' => 'clear-sample-data', 'type' => 'sys-defined'], ['mn' => ['text' => 'Жишиг дата цэвэрлэх'], 'en' => ['text' => 'Clear sample data']]);
        $model->insert(['keyword' => 'close', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хаах'], 'en' => ['text' => 'Close']]);
        $model->insert(['keyword' => 'closed', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хаагдсан'], 'en' => ['text' => 'Closed']]);
        $model->insert(['keyword' => 'code', 'type' => 'sys-defined'], ['mn' => ['text' => 'Код'], 'en' => ['text' => 'Code']]);
        $model->insert(['keyword' => 'colors', 'type' => 'sys-defined'], ['mn' => ['text' => 'Өнгөнүүд'], 'en' => ['text' => 'Colors']]);
        $model->insert(['keyword' => 'comment', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сэтгэгдэл'], 'en' => ['text' => 'Comment']]);
        $model->insert(['keyword' => 'comments', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сэтгэгдлүүд'], 'en' => ['text' => 'Comments']]);
        $model->insert(['keyword' => 'config', 'type' => 'sys-defined'], ['mn' => ['text' => 'Тохиргоо'], 'en' => ['text' => 'Config']]);
        $model->insert(['keyword' => 'confirm', 'type' => 'sys-defined'], ['mn' => ['text' => 'Батлах'], 'en' => ['text' => 'Confirm']]);
        $model->insert(['keyword' => 'confirm-deactivate', 'type' => 'sys-defined'], ['mn' => ['text' => 'Идэвхгүй болгохдоо итгэлтэй байна уу?'], 'en' => ['text' => 'Are you sure you want to deactivate?']]);
        $model->insert(['keyword' => 'confirm-delete', 'type' => 'sys-defined'], ['mn' => ['text' => 'Устгахдаа итгэлтэй байна уу?'], 'en' => ['text' => 'Are you sure you want to delete?']]);
        $model->insert(['keyword' => 'confirm-delete-request', 'type' => 'sys-defined'], ['mn' => ['text' => 'Та энэ хүсэлтийг устгахдаа итгэлтэй байна уу?'], 'en' => ['text' => 'Are you sure you want to delete this request?']]);
        $model->insert(['keyword' => 'confirm-open-file', 'type' => 'sys-defined'], ['mn' => ['text' => 'Та энэ файлыг нээхдээ итгэлтэй байна уу?'], 'en' => ['text' => 'Are you sure you want to open this file?']]);
        $model->insert(['keyword' => 'contact', 'type' => 'sys-defined'], ['mn' => ['text' => 'Холбоо барих'], 'en' => ['text' => 'Contact']]);
        $model->insert(['keyword' => 'contact-info', 'type' => 'sys-defined'], ['mn' => ['text' => 'Холбогдох мэдээлэл'], 'en' => ['text' => 'Contact Information']]);
        $model->insert(['keyword' => 'contact-us', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бидэнтэй холбогдох'], 'en' => ['text' => 'Contact Us']]);
        $model->insert(['keyword' => 'contacted-by-email', 'type' => 'sys-defined'], ['mn' => ['text' => 'Имэйлээр холбогдсон'], 'en' => ['text' => 'Contacted by email']]);
        $model->insert(['keyword' => 'contacted-by-phone', 'type' => 'sys-defined'], ['mn' => ['text' => 'Утсаар холбогдсон'], 'en' => ['text' => 'Contacted by phone']]);
        $model->insert(['keyword' => 'content', 'type' => 'sys-defined'], ['mn' => ['text' => 'Агуулга'], 'en' => ['text' => 'Content']]);
        $model->insert(['keyword' => 'continue', 'type' => 'sys-defined'], ['mn' => ['text' => 'Үргэлжлүүлэх'], 'en' => ['text' => 'Continue']]);
        $model->insert(['keyword' => 'copy-text-from', 'type' => 'sys-defined'], ['mn' => ['text' => 'Текст хуулбарлах хэл'], 'en' => ['text' => 'Copy texts from']]);
        $model->insert(['keyword' => 'create-new-user', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэрэглэгч шинээр үүсгэх'], 'en' => ['text' => 'Create new user']]);

        $model->insert(['keyword' => 'created-at', 'type' => 'sys-defined'], ['mn' => ['text' => 'Үүсгэсэн огноо'], 'en' => ['text' => 'Created at']]);
        $model->insert(['keyword' => 'created-by', 'type' => 'sys-defined'], ['mn' => ['text' => 'Үүсгэсэн хэрэглэгч'], 'en' => ['text' => 'Created by']]);
        $model->insert(['keyword' => 'customer', 'type' => 'sys-defined'], ['mn' => ['text' => 'Захиалагч'], 'en' => ['text' => 'Customer']]);
        $model->insert(['keyword' => 'dashboard', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хянах самбар'], 'en' => ['text' => 'Dashboard']]);
        $model->insert(['keyword' => 'data-delete-cannot-undo', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүх дата устгагдана. Энэ үйлдлийг буцаах боломжгүй!'], 'en' => ['text' => 'All data will be deleted. This action cannot be undone!']]);
        $model->insert(['keyword' => 'date', 'type' => 'sys-defined'], ['mn' => ['text' => 'Он сар'], 'en' => ['text' => 'Date']]);
        $model->insert(['keyword' => 'date-created', 'type' => 'sys-defined'], ['mn' => ['text' => 'Үүссэн огноо'], 'en' => ['text' => 'Date created']]);
        $model->insert(['keyword' => 'date-modified', 'type' => 'sys-defined'], ['mn' => ['text' => 'Өөрчлөгдсөн огноо'], 'en' => ['text' => 'Date modified']]);
        $model->insert(['keyword' => 'deactivated', 'type' => 'sys-defined'], ['mn' => ['text' => 'Устгагдсан'], 'en' => ['text' => 'Deactivated']]);
        $model->insert(['keyword' => 'delete', 'type' => 'sys-defined'], ['mn' => ['text' => 'Устгах'], 'en' => ['text' => 'Delete']]);
        $model->insert(['keyword' => 'delete-with-replies', 'type' => 'sys-defined'], ['mn' => ['text' => 'Энэ сэтгэгдлийн хариултууд мөн устгагдана'], 'en' => ['text' => 'All replies to this comment will also be deleted']]);

        $model->insert(['keyword' => 'description', 'type' => 'sys-defined'], ['mn' => ['text' => 'Тайлбар'], 'en' => ['text' => 'Description']]);
        $model->insert(['keyword' => 'detailed-description', 'type' => 'sys-defined'], ['mn' => ['text' => 'Дэлгэрэнгүй тайлбар'], 'en' => ['text' => 'Detailed description']]);
        $model->insert(['keyword' => 'dev-requests', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хөгжүүлэлтийн хүсэлт'], 'en' => ['text' => 'Dev Requests']]);
        $model->insert(['keyword' => 'developers', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хөгжүүлэгчид'], 'en' => ['text' => 'Developers']]);
        $model->insert(['keyword' => 'download', 'type' => 'sys-defined'], ['mn' => ['text' => 'Татах'], 'en' => ['text' => 'Download']]);
        $model->insert(['keyword' => 'edit-record', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бичлэг засах'], 'en' => ['text' => 'Edit Record']]);
        $model->insert(['keyword' => 'edit-user', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэрэглэгчийн мэдээлэл өөрчлөх'], 'en' => ['text' => 'Edit user information']]);
        $model->insert(['keyword' => 'email', 'type' => 'sys-defined'], ['mn' => ['text' => 'Имэйл'], 'en' => ['text' => 'Email']]);
        $model->insert(['keyword' => 'email-template-not-set', 'type' => 'sys-defined'], ['mn' => ['text' => 'Цахим захианы загварыг тодорхойлоогүй байна!'], 'en' => ['text' => 'Email template not found!']]);

        $model->insert(['keyword' => 'empty-directory', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хоосон хавтас'], 'en' => ['text' => 'Empty directory']]);
        $model->insert(['keyword' => 'empty-result', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хоосон үр дүн'], 'en' => ['text' => 'Empty result']]);
        $model->insert(['keyword' => 'enter-email-below', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүртгэлтэй имэйл хаягаа доор бичнэ үү!'], 'en' => ['text' => 'Enter your e-mail address!']]);
        $model->insert(['keyword' => 'enter-email-empty', 'type' => 'sys-defined'], ['mn' => ['text' => 'Имейл хаягыг оруулна уу'], 'en' => ['text' => 'Please enter email address']]);
        $model->insert(['keyword' => 'enter-language-details', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэлний мэдээллийг оруулна уу'], 'en' => ['text' => 'Provide language details']]);
        $model->insert(['keyword' => 'enter-personal-details', 'type' => 'sys-defined'], ['mn' => ['text' => 'Та доор хэсэгт хувийн мэдээллээ оруулна уу!'], 'en' => ['text' => 'Enter your personal details below!']]);
        $model->insert(['keyword' => 'enter-search-terms', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хайх утгаа оруулна уу..'], 'en' => ['text' => 'Enter search terms..']]);
        $model->insert(['keyword' => 'enter-valid-email', 'type' => 'sys-defined'], ['mn' => ['text' => 'Имэйл хаягыг зөв оруулна уу'], 'en' => ['text' => 'Please enter a valid email address']]);
        $model->insert(['keyword' => 'error', 'type' => 'sys-defined'], ['mn' => ['text' => 'Алдаа'], 'en' => ['text' => 'Error']]);
        $model->insert(['keyword' => 'error-existing-lang-code', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэлний кодыг системд ашиглаж байгаа тул өөр код сонгоно уу!'], 'en' => ['text' => 'Хэлний кодыг системд ашиглаж байгаа тул өөр код сонгоно уу!']]);
        $model->insert(['keyword' => 'error-lang-existing', 'type' => 'sys-defined'], ['mn' => ['text' => 'Системд хэлийг ашиглаж байгаа тул өөр хэл сонгоно уу!'], 'en' => ['text' => 'Системд хэлийг ашиглаж байгаа тул өөр хэл сонгоно уу!']]);
        $model->insert(['keyword' => 'error-lang-name-existing', 'type' => 'sys-defined'], ['mn' => ['text' => 'Системд хэлний нэрийг ашиглаж байгаа тул өөр нэр ашиглана уу!'], 'en' => ['text' => 'Системд хэлний нэрийг ашиглаж байгаа тул өөр нэр ашиглана уу!']]);
        $model->insert(['keyword' => 'error-occurred', 'type' => 'sys-defined'], ['mn' => ['text' => 'Алдаа гарлаа'], 'en' => ['text' => 'Error occurred']]);
        $model->insert(['keyword' => 'error-password-empty', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нууц үг талбарыг оруулна уу'], 'en' => ['text' => 'Please enter password']]);
        $model->insert(['keyword' => 'error-username-empty', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нэвтрэх нэр талбарыг оруулна уу'], 'en' => ['text' => 'Please enter username']]);
        $model->insert(['keyword' => 'error-username-cannot-change', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нэвтрэх нэрийг дараа өөрчлөх боломжгүй'], 'en' => ['text' => 'Username cannot be changed later']]);
        $model->insert(['keyword' => 'feature-on-homepage', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нүүр хуудсанд онцлох'], 'en' => ['text' => 'Feature on homepage']]);
        $model->insert(['keyword' => 'featured', 'type' => 'sys-defined'], ['mn' => ['text' => 'Онцлох'], 'en' => ['text' => 'Featured']]);
        $model->insert(['keyword' => 'field-is-required', 'type' => 'sys-defined'], ['mn' => ['text' => 'Талбарын утгыг оруулна уу'], 'en' => ['text' => 'This field is required']]);
        $model->insert(['keyword' => 'file', 'type' => 'sys-defined'], ['mn' => ['text' => 'Файл'], 'en' => ['text' => 'File']]);
        $model->insert(['keyword' => 'file-load-failed', 'type' => 'sys-defined'], ['mn' => ['text' => 'Файлын агуулгыг ачаалж чадсангүй'], 'en' => ['text' => 'Failed to load file content']]);
        $model->insert(['keyword' => 'files', 'type' => 'sys-defined'], ['mn' => ['text' => 'Файлууд'], 'en' => ['text' => 'Files']]);
        $model->insert(['keyword' => 'files-excludes-note', 'type' => 'sys-defined'], ['mn' => ['text' => 'контентын толгой зураг, агуулгын файл тооцогдоогүй'], 'en' => ['text' => 'excludes header images & inline files']]);
        $model->insert(['keyword' => 'fill-new-password', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинэ нууц үгийг оруулна уу!'], 'en' => ['text' => 'Please fill a new password!']]);
        $model->insert(['keyword' => 'fill-required-fields', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шаардлагатай талбаруудыг бөглөнө үү'], 'en' => ['text' => 'Please fill in the required fields']]);
        $model->insert(['keyword' => 'filter', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шүүлтүүр'], 'en' => ['text' => 'Filter']]);
        $model->insert(['keyword' => 'firstname', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нэр'], 'en' => ['text' => 'First Name']]);
        $model->insert(['keyword' => 'flag', 'type' => 'sys-defined'], ['mn' => ['text' => 'Далбаа'], 'en' => ['text' => 'Flag']]);
        $model->insert(['keyword' => 'follow-us', 'type' => 'sys-defined'], ['mn' => ['text' => 'Биднийг дагах'], 'en' => ['text' => 'Follow Us']]);
        $model->insert(['keyword' => 'forgot-password', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нууц үгээ мартсан уу?'], 'en' => ['text' => 'Forgot password?']]);
        $model->insert(['keyword' => 'general-info', 'type' => 'sys-defined'], ['mn' => ['text' => 'Ерөнхий мэдээлэл'], 'en' => ['text' => 'General Info']]);
        $model->insert(['keyword' => 'group', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүлэг'], 'en' => ['text' => 'Group']]);

        $model->insert(['keyword' => 'home', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нүүр'], 'en' => ['text' => 'Home']]);

        $model->insert(['keyword' => 'image', 'type' => 'sys-defined'], ['mn' => ['text' => 'Зураг'], 'en' => ['text' => 'Image']]);
        $model->insert(['keyword' => 'in-progress', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хийгдэж буй'], 'en' => ['text' => 'In progress']]);
        $model->insert(['keyword' => 'invalid-request', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хүсэлт буруу байна!'], 'en' => ['text' => 'Request is not valid!']]);
        $model->insert(['keyword' => 'invalid-values', 'type' => 'sys-defined'], ['mn' => ['text' => 'Утга буруу байна!'], 'en' => ['text' => 'Invalid values!']]);
        $model->insert(['keyword' => 'items', 'type' => 'sys-defined'], ['mn' => ['text' => 'зүйл'], 'en' => ['text' => 'items']]);

        $model->insert(['keyword' => 'keyword', 'type' => 'sys-defined'], ['mn' => ['text' => 'Түлхүүр үг'], 'en' => ['text' => 'Keyword']]);
        $model->insert(['keyword' => 'keyword-existing-in', 'type' => 'sys-defined'], ['mn' => ['text' => 'Түлхүүр үг давхцаж байна'], 'en' => ['text' => 'Keyword existing in']]);
        $model->insert(['keyword' => 'language', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэл'], 'en' => ['text' => 'Language']]);
        $model->insert(['keyword' => 'lastname', 'type' => 'sys-defined'], ['mn' => ['text' => 'Овог'], 'en' => ['text' => 'Last name']]);

        $model->insert(['keyword' => 'lines', 'type' => 'sys-defined'], ['mn' => ['text' => 'мөр'], 'en' => ['text' => 'lines']]);
        $model->insert(['keyword' => 'link', 'type' => 'sys-defined'], ['mn' => ['text' => 'Холбоос'], 'en' => ['text' => 'Link']]);
        $model->insert(['keyword' => 'link-must-be-url', 'type' => 'sys-defined'], ['mn' => ['text' => 'Холбоос нь URL (http://...) эсвэл локал зам (/path) байх ёстой'], 'en' => ['text' => 'Link must be a URL (http://...) or a local path (/path)']]);
        $model->insert(['keyword' => 'link-page-content-warning', 'type' => 'sys-defined'], ['mn' => ['text' => 'Энэ хуудас холбоос агуулж байна. Ерөнхий зарчмаар бол холбоостой хуудасны агуулга ашиглагдахгүй бөгөөд цэсэнд байрлах үед заасан холбоос руу шууд чиглүүлдэг.'], 'en' => ['text' => 'This page contains a link. As a general rule, the content of a linked page is not used - when placed in the menu, it redirects directly to the specified link.']]);
        $model->insert(['keyword' => 'list', 'type' => 'sys-defined'], ['mn' => ['text' => 'Жагсаалт'], 'en' => ['text' => 'List']]);
        $model->insert(['keyword' => 'loading', 'type' => 'sys-defined'], ['mn' => ['text' => 'Ачаалж байна'], 'en' => ['text' => 'Loading']]);
        $model->insert(['keyword' => 'loading-statistics', 'type' => 'sys-defined'], ['mn' => ['text' => 'Статистик уншиж байна...'], 'en' => ['text' => 'Loading statistics...']]);
        $model->insert(['keyword' => 'localization', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нутагшуулалт'], 'en' => ['text' => 'Localization']]);
        $model->insert(['keyword' => 'log', 'type' => 'sys-defined'], ['mn' => ['text' => 'Протокол'], 'en' => ['text' => 'Log']]);
        $model->insert(['keyword' => 'log-file-empty', 'type' => 'sys-defined'], ['mn' => ['text' => 'Log файл хоосон байна'], 'en' => ['text' => 'Log file is empty']]);
        $model->insert(['keyword' => 'login', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нэвтрэх'], 'en' => ['text' => 'Login']]);
        $model->insert(['keyword' => 'logo', 'type' => 'sys-defined'], ['mn' => ['text' => 'Лого'], 'en' => ['text' => 'Logo']]);
        $model->insert(['keyword' => 'logout', 'type' => 'sys-defined'], ['mn' => ['text' => 'Гарах'], 'en' => ['text' => 'Logout']]);
        $model->insert(['keyword' => 'logs', 'type' => 'sys-defined'], ['mn' => ['text' => 'Протокол'], 'en' => ['text' => 'Logs']]);

        $model->insert(['keyword' => 'manual', 'type' => 'sys-defined'], ['mn' => ['text' => 'Гарын авлага'], 'en' => ['text' => 'Manual']]);
        $model->insert(['keyword' => 'manual-not-ready', 'type' => 'sys-defined'], ['mn' => ['text' => 'Гарын авлага бэлтгэгдээгүй байна'], 'en' => ['text' => 'Manual is not yet available']]);
        $model->insert(['keyword' => 'menu', 'type' => 'sys-defined'], ['mn' => ['text' => 'Меню'], 'en' => ['text' => 'Menu']]);
        $model->insert(['keyword' => 'message', 'type' => 'sys-defined'], ['mn' => ['text' => 'Зурвас'], 'en' => ['text' => 'Message']]);
        $model->insert(['keyword' => 'messages', 'type' => 'sys-defined'], ['mn' => ['text' => 'Мессежүүд'], 'en' => ['text' => 'Messages']]);
        $model->insert(['keyword' => 'meta', 'type' => 'sys-defined'], ['mn' => ['text' => 'Мета'], 'en' => ['text' => 'Meta']]);

        $model->insert(['keyword' => 'multiple-files-allowed', 'type' => 'sys-defined'], ['mn' => ['text' => 'Олон файл сонгох боломжтой'], 'en' => ['text' => 'Multiple files allowed']]);
        $model->insert(['keyword' => 'name', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нэр'], 'en' => ['text' => 'Name']]);
        $model->insert(['keyword' => 'navigation', 'type' => 'sys-defined'], ['mn' => ['text' => 'Навигац'], 'en' => ['text' => 'Navigation']]);
        $model->insert(['keyword' => 'network-error', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сүлжээний алдаа'], 'en' => ['text' => 'Network error']]);
        $model->insert(['keyword' => 'new', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинэ'], 'en' => ['text' => 'New']]);
        $model->insert(['keyword' => 'new-password', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинэ нууц үг'], 'en' => ['text' => 'New Password']]);
        $model->insert(['keyword' => 'new-request', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинэ хүсэлт'], 'en' => ['text' => 'New request']]);
        $model->insert(['keyword' => 'newer', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинэ'], 'en' => ['text' => 'Newer']]);
        $model->insert(['keyword' => 'news', 'type' => 'sys-defined'], ['mn' => ['text' => 'Мэдээлэл'], 'en' => ['text' => 'News']]);
        $model->insert(['keyword' => 'news-archive', 'type' => 'sys-defined'], ['mn' => ['text' => 'Мэдээний архив'], 'en' => ['text' => 'News Archive']]);

        $model->insert(['keyword' => 'no', 'type' => 'sys-defined'], ['mn' => ['text' => 'Үгүй'], 'en' => ['text' => 'No']]);
        $model->insert(['keyword' => 'no-change', 'type' => 'sys-defined'], ['mn' => ['text' => 'Өөрчлөхгүй'], 'en' => ['text' => 'No change']]);
        $model->insert(['keyword' => 'no-data-found', 'type' => 'sys-defined'], ['mn' => ['text' => 'Мэдээлэл олдсонгүй'], 'en' => ['text' => 'No data found']]);
        $model->insert(['keyword' => 'no-email', 'type' => 'sys-defined'], ['mn' => ['text' => 'Имэйл байхгүй'], 'en' => ['text' => 'No email']]);
        $model->insert(['keyword' => 'no-more-logs', 'type' => 'sys-defined'], ['mn' => ['text' => 'Цааш лог байхгүй'], 'en' => ['text' => 'No more logs']]);
        $model->insert(['keyword' => 'no-news-found', 'type' => 'sys-defined'], ['mn' => ['text' => 'Мэдээ олдсонгүй'], 'en' => ['text' => 'No news found']]);
        $model->insert(['keyword' => 'no-products-found', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүтээгдэхүүн олдсонгүй'], 'en' => ['text' => 'No products available']]);
        $model->insert(['keyword' => 'no-record-selected', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бичлэг сонгогдоогүй байна'], 'en' => ['text' => 'No record selected']]);
        $model->insert(['keyword' => 'no-responses-yet', 'type' => 'sys-defined'], ['mn' => ['text' => 'Одоогоор хариулт байхгүй'], 'en' => ['text' => 'No responses yet']]);
        $model->insert(['keyword' => 'no-results-found', 'type' => 'sys-defined'], ['mn' => ['text' => 'Үр дүн олдсонгүй'], 'en' => ['text' => 'No results found']]);
        $model->insert(['keyword' => 'notice', 'type' => 'sys-defined'], ['mn' => ['text' => 'Мэдэгдэл'], 'en' => ['text' => 'Notice']]);
        $model->insert(['keyword' => 'older', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хуучин'], 'en' => ['text' => 'Older']]);
        $model->insert(['keyword' => 'optional', 'type' => 'sys-defined'], ['mn' => ['text' => 'Заавал биш'], 'en' => ['text' => 'Optional']]);
        $model->insert(['keyword' => 'options', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сонголтууд'], 'en' => ['text' => 'Options']]);
        $model->insert(['keyword' => 'order', 'type' => 'sys-defined'], ['mn' => ['text' => 'Захиалга'], 'en' => ['text' => 'Order']]);
        $model->insert(['keyword' => 'order-now', 'type' => 'sys-defined'], ['mn' => ['text' => 'Захиалах'], 'en' => ['text' => 'Order Now']]);
        $model->insert(['keyword' => 'order-product', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүтээгдэхүүн захиалах'], 'en' => ['text' => 'Order Product']]);
        $model->insert(['keyword' => 'order-submitted', 'type' => 'sys-defined'], ['mn' => ['text' => 'Захиалга амжилттай илгээгдлээ!'], 'en' => ['text' => 'Order submitted successfully!']]);
        $model->insert(['keyword' => 'order-thank-you', 'type' => 'sys-defined'], ['mn' => ['text' => 'Баярлалаа, %s. Бид тантай удахгүй холбогдоно.'], 'en' => ['text' => 'Thank you, %s. We will contact you shortly.']]);
        $model->insert(['keyword' => 'orders', 'type' => 'sys-defined'], ['mn' => ['text' => 'Захиалгууд'], 'en' => ['text' => 'Orders']]);
        $model->insert(['keyword' => 'organization', 'type' => 'sys-defined'], ['mn' => ['text' => 'Байгууллага'], 'en' => ['text' => 'Organization']]);
        $model->insert(['keyword' => 'organizations', 'type' => 'sys-defined'], ['mn' => ['text' => 'Байгууллагууд'], 'en' => ['text' => 'Organizations']]);
        $model->insert(['keyword' => 'other', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бусад'], 'en' => ['text' => 'Other']]);
        $model->insert(['keyword' => 'other-users', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бусад хэрэглэгчид'], 'en' => ['text' => 'Other users']]);
        $model->insert(['keyword' => 'page', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хуудас'], 'en' => ['text' => 'Page']]);
        $model->insert(['keyword' => 'pages', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хуудсууд'], 'en' => ['text' => 'Pages']]);
        $model->insert(['keyword' => 'pages-navigation', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хуудасны навигац'], 'en' => ['text' => 'Pages Navigation']]);
        $model->insert(['keyword' => 'parent', 'type' => 'sys-defined'], ['mn' => ['text' => 'Эцэг'], 'en' => ['text' => 'Parent']]);
        $model->insert(['keyword' => 'parent-page-content-warning', 'type' => 'sys-defined'], ['mn' => ['text' => 'Энэ хуудас дотроо дэд хуудсууд агуулж байгаа тул агуулга/холбоос нь шууд ашиглагдахгүй байж болно. Ерөнхий зарчмаар бол эцэг хуудас нь цэсний толгой үүрэг гүйцэтгэдэг бөгөөд зөвхөн хамгийн сүүлийн шатны хүүхэд хуудасны агуулга л нээгдэж уншигддаг.'], 'en' => ['text' => 'This page contains sub-pages, so its content may not be displayed directly. As a general rule, parent pages serve as menu headers and only the content of the deepest child page is opened and read.']]);
        $model->insert(['keyword' => 'password', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нууц үг'], 'en' => ['text' => 'Password']]);
        $model->insert(['keyword' => 'password-must-confirm', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нууц үгийг давтан бичих хэрэгтэй'], 'en' => ['text' => 'Please re-enter the password']]);
        $model->insert(['keyword' => 'password-must-match', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нууц үгийг давтан бичихдээ зөв оруулах хэрэгтэй'], 'en' => ['text' => 'Password entries must match']]);
        $model->insert(['keyword' => 'password-reset', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нууц үг шинээр тааруулах'], 'en' => ['text' => 'Password reset']]);
        $model->insert(['keyword' => 'pending', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хүлээгдэж буй'], 'en' => ['text' => 'Pending']]);
        $model->insert(['keyword' => 'personal-info', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хувийн мэдээлэл'], 'en' => ['text' => 'Personal Info']]);
        $model->insert(['keyword' => 'phone', 'type' => 'sys-defined'], ['mn' => ['text' => 'Утас'], 'en' => ['text' => 'Phone']]);
        $model->insert(['keyword' => 'photo', 'type' => 'sys-defined'], ['mn' => ['text' => 'Зураг'], 'en' => ['text' => 'Photo']]);
        $model->insert(['keyword' => 'please-confirm-info', 'type' => 'sys-defined'], ['mn' => ['text' => 'Мэдээллийг баталгаажуулна уу'], 'en' => ['text' => 'Please confirm infomations']]);
        $model->insert(['keyword' => 'position', 'type' => 'sys-defined'], ['mn' => ['text' => 'Байршил'], 'en' => ['text' => 'Position']]);
        $model->insert(['keyword' => 'price', 'type' => 'sys-defined'], ['mn' => ['text' => 'Үнэ'], 'en' => ['text' => 'Price']]);
        $model->insert(['keyword' => 'privacy-policy', 'type' => 'sys-defined'], ['mn' => ['text' => 'хувийн нууцлалын бодлого'], 'en' => ['text' => 'privacy policy']]);

        $model->insert(['keyword' => 'product', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүтээгдэхүүн'], 'en' => ['text' => 'Product']]);
        $model->insert(['keyword' => 'products', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүтээгдэхүүн'], 'en' => ['text' => 'Products']]);
        $model->insert(['keyword' => 'properties', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинж чанарууд'], 'en' => ['text' => 'Properties']]);
        $model->insert(['keyword' => 'publish', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нийтлэх'], 'en' => ['text' => 'Publish']]);
        $model->insert(['keyword' => 'published', 'type' => 'sys-defined'], ['mn' => ['text' => 'нийтэлсэн'], 'en' => ['text' => 'published']]);
        $model->insert(['keyword' => 'quantity', 'type' => 'sys-defined'], ['mn' => ['text' => 'Тоо ширхэг'], 'en' => ['text' => 'Quantity']]);
        $model->insert(['keyword' => 'read', 'type' => 'sys-defined'], ['mn' => ['text' => 'Уншсан'], 'en' => ['text' => 'Read']]);
        $model->insert(['keyword' => 'read-more', 'type' => 'sys-defined'], ['mn' => ['text' => 'Дэлгэрэнгүй'], 'en' => ['text' => 'Read more']]);
        $model->insert(['keyword' => 'recent-news', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сүүлийн үеийн мэдээ'], 'en' => ['text' => 'Recent News']]);
        $model->insert(['keyword' => 'replied', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хариулсан'], 'en' => ['text' => 'Replied']]);
        $model->insert(['keyword' => 'record-insert-error', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бичлэг нэмэх явцад алдаа гарлаа'], 'en' => ['text' => 'Error occurred while inserting record']]);
        $model->insert(['keyword' => 'record-insert-success', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бичлэг амжилттай нэмэгдлээ'], 'en' => ['text' => 'Record successfully added']]);
        $model->insert(['keyword' => 'record-successfully-deleted', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бичлэг амжилттай устлаа'], 'en' => ['text' => 'Record successfully deleted']]);
        $model->insert(['keyword' => 'record-update-success', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бичлэг амжилттай засагдлаа'], 'en' => ['text' => 'Record successfully updated']]);
        $model->insert(['keyword' => 'reference-tables', 'type' => 'sys-defined'], ['mn' => ['text' => 'Лавлах хүснэгтүүд'], 'en' => ['text' => 'Reference Tables']]);
        $model->insert(['keyword' => 'refresh', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинэчлэх'], 'en' => ['text' => 'Refresh']]);
        $model->insert(['keyword' => 'remove', 'type' => 'sys-defined'], ['mn' => ['text' => 'Арилгах'], 'en' => ['text' => 'Remove']]);
        $model->insert(['keyword' => 'reply', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хариулах'], 'en' => ['text' => 'Reply']]);
        $model->insert(['keyword' => 'reply-method', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хариулсан арга'], 'en' => ['text' => 'Reply method']]);
        $model->insert(['keyword' => 'request', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хүсэлт'], 'en' => ['text' => 'Request']]);
        $model->insert(['keyword' => 'request-closed', 'type' => 'sys-defined'], ['mn' => ['text' => 'Энэ хүсэлт хаагдсан байна'], 'en' => ['text' => 'This request has been closed']]);
        $model->insert(['keyword' => 'request-new-user', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүртгүүлэх хүсэлт'], 'en' => ['text' => 'Signup requests']]);
        $model->insert(['keyword' => 'request-registered-success', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хүсэлт амжилттай бүртгэгдлээ'], 'en' => ['text' => 'Request registered successfully']]);
        $model->insert(['keyword' => 'reset-and-start-production', 'type' => 'sys-defined'], ['mn' => ['text' => 'Reset & Production эхлэх'], 'en' => ['text' => 'Reset & Start Production']]);
        $model->insert(['keyword' => 'reset-email-sent', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нууц үгийг шинэчлэх зааврыг амжилттай илгээлээ.<br />Та заасан имейл хаягаа шалгаж зааврын дагуу нууц үгээ шинэчлэнэ үү!'], 'en' => ['text' => 'An reset e-mail has been sent.<br />Please check your email for further instructions!']]);
        $model->insert(['keyword' => 'reset-only-sample-data', 'type' => 'sys-defined'], ['mn' => ['text' => 'Зөвхөн жишиг дата байгаа үед reset хийх боломжтой'], 'en' => ['text' => 'Reset is only available when only sample data exists']]);

        $model->insert(['keyword' => 'resolved', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шийдвэрлэсэн'], 'en' => ['text' => 'Resolved']]);
        $model->insert(['keyword' => 'responses', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хариултууд'], 'en' => ['text' => 'Responses']]);
        $model->insert(['keyword' => 'result-count', 'type' => 'sys-defined'], ['mn' => ['text' => 'үр дүн'], 'en' => ['text' => 'result(s)']]);
        $model->insert(['keyword' => 'retype-new-password', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинэ нууц үгийг давтах'], 'en' => ['text' => 'Re-type New Password']]);
        $model->insert(['keyword' => 'retype-password', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нууц үгээ дахин бичнэ'], 'en' => ['text' => 'Re-type Password']]);
        $model->insert(['keyword' => 'role', 'type' => 'sys-defined'], ['mn' => ['text' => 'Дүр'], 'en' => ['text' => 'Role']]);
        $model->insert(['keyword' => 'rows-found', 'type' => 'sys-defined'], ['mn' => ['text' => 'мөр олдлоо'], 'en' => ['text' => 'rows found']]);
        $model->insert(['keyword' => 'running', 'type' => 'sys-defined'], ['mn' => ['text' => 'Ажиллаж байна'], 'en' => ['text' => 'Running']]);

        $model->insert(['keyword' => 'sale-price', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хямдралтай үнэ'], 'en' => ['text' => 'Sale Price']]);
        $model->insert(['keyword' => 'sample-data-cleared', 'type' => 'sys-defined'], ['mn' => ['text' => 'Жишиг дата амжилттай цэвэрлэгдлээ. Production эхэлж байна!'], 'en' => ['text' => 'Sample data cleared. Production started!']]);
        $model->insert(['keyword' => 'sample-data-info', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хүснэгтэд жишиг (sample) дата байна. Production эхлүүлэхийн тулд цэвэрлэж болно.'], 'en' => ['text' => 'Table contains sample data. You can clear it to start production.']]);
        $model->insert(['keyword' => 'save', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хадгалах'], 'en' => ['text' => 'Save']]);

        $model->insert(['keyword' => 'search', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хайх'], 'en' => ['text' => 'Search']]);
        $model->insert(['keyword' => 'select', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сонгох'], 'en' => ['text' => 'Select']]);
        $model->insert(['keyword' => 'select-an-image', 'type' => 'sys-defined'], ['mn' => ['text' => 'Зураг сонгох'], 'en' => ['text' => 'Select an Image']]);
        $model->insert(['keyword' => 'select-at-least-one-filter', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шүүлтүүрээс ядаж нэгийг сонгоорой'], 'en' => ['text' => 'Select at least one filter']]);
        $model->insert(['keyword' => 'select-files', 'type' => 'sys-defined'], ['mn' => ['text' => 'Файлуудыг сонгох'], 'en' => ['text' => 'Select Files']]);

        $model->insert(['keyword' => 'select-text-settings', 'type' => 'sys-defined'], ['mn' => ['text' => 'Текстийн тохиргоог сонгоно уу'], 'en' => ['text' => 'Select text settings']]);
        $model->insert(['keyword' => 'send', 'type' => 'sys-defined'], ['mn' => ['text' => 'Илгээх'], 'en' => ['text' => 'Send']]);
        $model->insert(['keyword' => 'send-message', 'type' => 'sys-defined'], ['mn' => ['text' => 'Мессеж илгээх'], 'en' => ['text' => 'Send a Message']]);
        $model->insert(['keyword' => 'sending', 'type' => 'sys-defined'], ['mn' => ['text' => 'Илгээж байна'], 'en' => ['text' => 'Sending']]);
        $model->insert(['keyword' => 'server-error', 'type' => 'sys-defined'], ['mn' => ['text' => 'Серверийн алдаа'], 'en' => ['text' => 'Server error']]);
        $model->insert(['keyword' => 'sent-by', 'type' => 'sys-defined'], ['mn' => ['text' => 'Илгээсэн'], 'en' => ['text' => 'Sent by']]);
        $model->insert(['keyword' => 'set-new-password', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэрэглэгчийн шинэ нууц үгийг тохируулна уу!'], 'en' => ['text' => 'Please set a new password for the user!']]);
        $model->insert(['keyword' => 'set-new-password-success', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нууц үгийг шинээр тохирууллаа. Шинэ нууц үгээ ашиглана уу'], 'en' => ['text' => 'Your password has been changed successfully! Thank you']]);
        $model->insert(['keyword' => 'settings', 'type' => 'sys-defined'], ['mn' => ['text' => 'Тохиргоонууд'], 'en' => ['text' => 'Settings']]);
        $model->insert(['keyword' => 'short-description', 'type' => 'sys-defined'], ['mn' => ['text' => 'Товч тайлбар'], 'en' => ['text' => 'Short description']]);
        $model->insert(['keyword' => 'signin', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нэвтрэх'], 'en' => ['text' => 'Sign In']]);
        $model->insert(['keyword' => 'signup', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүртгүүлэх'], 'en' => ['text' => 'Sign Up']]);
        $model->insert(['keyword' => 'signup-agree-middle', 'type' => 'sys-defined'], ['mn' => ['text' => 'хүлээн зөвшөөрч,'], 'en' => ['text' => 'and have read our']]);
        $model->insert(['keyword' => 'signup-agree-prefix', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүртгүүлэх товчыг дарснаар, та манай'], 'en' => ['text' => 'By clicking Sign Up, you agree to our']]);
        $model->insert(['keyword' => 'sitemap', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сайтын бүтэц'], 'en' => ['text' => 'Site map']]);
        $model->insert(['keyword' => 'size', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэмжээ'], 'en' => ['text' => 'Size']]);
        $model->insert(['keyword' => 'sizes', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэмжээнүүд'], 'en' => ['text' => 'Sizes']]);
        $model->insert(['keyword' => 'social-media', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сошиал хаягууд'], 'en' => ['text' => 'Social Media']]);
        $model->insert(['keyword' => 'something-went-wrong', 'type' => 'sys-defined'], ['mn' => ['text' => 'Ямар нэгэн саатал учирлаа'], 'en' => ['text' => 'Looks like something went wrong']]);
        $model->insert(['keyword' => 'source', 'type' => 'sys-defined'], ['mn' => ['text' => 'Эх сурвалж'], 'en' => ['text' => 'Source']]);
        $model->insert(['keyword' => 'status', 'type' => 'sys-defined'], ['mn' => ['text' => 'Төлөв'], 'en' => ['text' => 'Status']]);
        $model->insert(['keyword' => 'stock', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нөөцийн тоо'], 'en' => ['text' => 'Stock']]);
        $model->insert(['keyword' => 'submit', 'type' => 'sys-defined'], ['mn' => ['text' => 'Батлах'], 'en' => ['text' => 'Submit']]);
        $model->insert(['keyword' => 'submit-order', 'type' => 'sys-defined'], ['mn' => ['text' => 'Захиалга илгээх'], 'en' => ['text' => 'Submit Order']]);
        $model->insert(['keyword' => 'success', 'type' => 'sys-defined'], ['mn' => ['text' => 'Амжилттай'], 'en' => ['text' => 'Success']]);
        $model->insert(['keyword' => 'system-no-permission', 'type' => 'sys-defined'], ['mn' => ['text' => 'Уучлаарай, таньд энэ мэдээлэлд хандах эрх олгогдоогүй байна!'], 'en' => ['text' => 'Access Denied, You don\'t have permission to access on this resource!']]);
        $model->insert(['keyword' => 'tables', 'type' => 'sys-defined'], ['mn' => ['text' => 'хүснэгт'], 'en' => ['text' => 'tables']]);

        $model->insert(['keyword' => 'telephone', 'type' => 'sys-defined'], ['mn' => ['text' => 'Утас'], 'en' => ['text' => 'Telephone']]);

        $model->insert(['keyword' => 'terms-and-conditions', 'type' => 'sys-defined'], ['mn' => ['text' => 'үйлчилгээний нөхцөл'], 'en' => ['text' => 'terms and conditions']]);
        $model->insert(['keyword' => 'text-settings', 'type' => 'sys-defined'], ['mn' => ['text' => 'Текстийн тохиргоо'], 'en' => ['text' => 'Text Settings']]);
        $model->insert(['keyword' => 'thank-you', 'type' => 'sys-defined'], ['mn' => ['text' => 'Баярлалаа!'], 'en' => ['text' => 'Thank you!']]);
        $model->insert(['keyword' => 'title', 'type' => 'sys-defined'], ['mn' => ['text' => 'Гарчиг'], 'en' => ['text' => 'Title']]);

        $model->insert(['keyword' => 'to-complete-registration-check-email', 'type' => 'sys-defined'], ['mn' => ['text' => 'Танд баярлалаа. Бүртгэлээ баталгаажуулахын тулд заасан имейлээ шалгана уу'], 'en' => ['text' => 'Thank you. To complete your registration please check your email']]);
        $model->insert(['keyword' => 'type', 'type' => 'sys-defined'], ['mn' => ['text' => 'Төрөл'], 'en' => ['text' => 'Type']]);
        $model->insert(['keyword' => 'u-have-some-form-errors', 'type' => 'sys-defined'], ['mn' => ['text' => 'Та мэдээллийг алдаатай бөглөсөн байна. Доорх талбаруудаа шалгана уу'], 'en' => ['text' => 'You have some form errors. Please check below']]);
        $model->insert(['keyword' => 'update', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинэчлэх'], 'en' => ['text' => 'Update']]);

        $model->insert(['keyword' => 'updated-at', 'type' => 'sys-defined'], ['mn' => ['text' => 'Шинэчлэгдсэн огноо'], 'en' => ['text' => 'Updated at']]);
        $model->insert(['keyword' => 'updated-by', 'type' => 'sys-defined'], ['mn' => ['text' => 'Өөрчилсөн хэрэглэгч'], 'en' => ['text' => 'Modified by']]);
        $model->insert(['keyword' => 'upload-files', 'type' => 'sys-defined'], ['mn' => ['text' => 'Файлуудыг илгээх'], 'en' => ['text' => 'Upload Files']]);
        $model->insert(['keyword' => 'urgent', 'type' => 'sys-defined'], ['mn' => ['text' => 'Яаралтай'], 'en' => ['text' => 'Urgent']]);
        $model->insert(['keyword' => 'usefull-links', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэрэгтэй холбоосууд'], 'en' => ['text' => 'Usefull Links']]);
        $model->insert(['keyword' => 'user', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэрэглэгч'], 'en' => ['text' => 'User']]);
        $model->insert(['keyword' => 'user-exists', 'type' => 'sys-defined'], ['mn' => ['text' => 'Заасан мэдээлэл бүхий хэрэглэгч аль хэдийн бүртгэгдсэн байна'], 'en' => ['text' => 'It looks like information belongs to an existing user']]);
        $model->insert(['keyword' => 'username', 'type' => 'sys-defined'], ['mn' => ['text' => 'Нэвтрэх нэр'], 'en' => ['text' => 'Username']]);
        $model->insert(['keyword' => 'users', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хэрэглэгчид'], 'en' => ['text' => 'Users']]);
        $model->insert(['keyword' => 'version', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хувилбар'], 'en' => ['text' => 'Version']]);
        $model->insert(['keyword' => 'view', 'type' => 'sys-defined'], ['mn' => ['text' => 'Харах'], 'en' => ['text' => 'View']]);
        $model->insert(['keyword' => 'view-all', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бүгдийг харах'], 'en' => ['text' => 'View All']]);

        $model->insert(['keyword' => 'view-record', 'type' => 'sys-defined'], ['mn' => ['text' => 'Бичлэг харах'], 'en' => ['text' => 'View record']]);
        $model->insert(['keyword' => 'visible-on-site', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сайт дээр харагдах'], 'en' => ['text' => 'Visible on site']]);
        $model->insert(['keyword' => 'warning', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сануулга'], 'en' => ['text' => 'Warning']]);
        $model->insert(['keyword' => 'working-hours', 'type' => 'sys-defined'], ['mn' => ['text' => 'Ажлын цаг'], 'en' => ['text' => 'Working Hours']]);
        $model->insert(['keyword' => 'write-comment', 'type' => 'sys-defined'], ['mn' => ['text' => 'Сэтгэгдэл бичих'], 'en' => ['text' => 'Write a comment']]);
        $model->insert(['keyword' => 'write-response', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хариулт бичих'], 'en' => ['text' => 'Write response']]);
        $model->insert(['keyword' => 'write-response-here', 'type' => 'sys-defined'], ['mn' => ['text' => 'Хариултаа энд бичнэ үү...'], 'en' => ['text' => 'Write your response here...']]);

        $model->insert(['keyword' => 'yes', 'type' => 'sys-defined'], ['mn' => ['text' => 'Тийм'], 'en' => ['text' => 'Yes']]);
        $model->insert(['keyword' => 'yes-clear-it', 'type' => 'sys-defined'], ['mn' => ['text' => 'Тийм, цэвэрлэх'], 'en' => ['text' => 'Yes, clear it']]);
    }
}
