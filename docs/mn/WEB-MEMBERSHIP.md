# Вэб гишүүнчлэл ба "Sign in with Google" - Хэрэгжүүлэх заавар

Raptor нь олон нийтийн вэб дээр зочны сагс, захиалгыг (`Web\Shop\Cart`, `/cart`, `/order`) бэлэн өгдөг ч **хэрэглэгчийн бүртгэл (гишүүнчлэл) агуулдаггүй**. Энэ нь зориудаар: учир нь Raptor дээр бүтээгдсэн ихэнх сайт (мэдээ, хуудас, байгууллагын портал) зочны бүртгэл огт шаарддаггүй. Гэтэл гишүүнчлэлийн дэд систем (бүртгүүлэх, имэйл баталгаажуулах, нууц үг сэргээх, social login, профайл, захиалгын түүх, бүртгэл устгах) нь түүнийг ашигладаггүй төсөл бүрийн layout, routing, session, CSRF-д хүрэх ёстой болно.

Иймээс энэ заавар нь олон нийтийн вэб дээрээ гишүүнчлэл шаардлагатай төсөлд зориулагдсан. Доорх дүрмийг баримтлан тухайн төсөл дотроо хийснээр бүх зүйл нэг хавтсанд төвлөрч, одоо байгаа дэлгүүртэй цөөн жижиг цэгээр холбогдоно.

---

## 1. Бүтэц

Бүх кодыг нэг модулийн хавтсанд байлгана - тэр хавтсыг болон хэдхэн мөрийг устгахад бүрэн арилдаг байх ёстой:

```
application/web/account/
  AccountController.php     # бүртгүүлэх, нэвтрэх, гарах, профайл, захиалгын түүх
  GoogleAuthController.php  # OAuth redirect + callback
  CustomersModel.php        # customers хүснэгт
  login.html, register.html, profile.html, orders.html
```

`Web\Account\` namespace-ийг `composer.json`-ийн `autoload.psr-4`-д бүртгээд `composer dump-autoload` ажиллуулна.

## 2. Хэрэглэгч нь dashboard-ын хэрэглэгч БИШ

Зочдыг тусдаа хүснэгтэд (жишээ нь `customers`) хадгална, хэзээ ч `users`-д биш. `users` хүснэгт dashboard-ын RBAC-ийг удирддаг; тэнд хэрэглэгч холих нь эрхийн шалгалтын нэг л алдаа dashboard руу нэвтрэх эрх болно гэсэн үг. Төлөвшсөн платформ бүр эдгээрийг салгадаг (Sylius `ShopUser` ба `AdminUser`, Laravel-ийн тусдаа guard).

Санал болгох баганууд:

| Багана | Тайлбар |
|---|---|
| `id` | primary |
| `email` | unique, жижиг үсгээр |
| `password` | `password_hash()`; зөвхөн Google-ээр нэвтэрдэг бол NULL |
| `google_sub` | unique, холбогдоогүй бол NULL |
| `name`, `phone`, `address` | захиалгын формыг урьдчилан бөглөнө |
| `email_verified_at` | datetime |
| `is_active` | soft delete / хаах |
| `created_at`, `updated_at` | |

Хүснэгтийг Model өөрөө үүсгэнэ (migration-д CREATE TABLE бичихгүй). Захиалгыг бүртгэлтэй холбохын тулд `products_orders`-д nullable `customer_id` баганыг migration-аар нэмнэ (`ALTER TABLE products_orders ADD COLUMN customer_id BIGINT`).

## 3. Session

- Нэвтэрсэн хэрэглэгчийг тусдаа session key-д хадгална, жишээ нь `$_SESSION['RAPTOR_WEB_CUSTOMER_ID']` - session нь dashboard-той хуваалцдаг тул dashboard-ын key-г хэзээ ч ашиглахгүй.
- Session-д бичдэг route бүр (нэвтрэх, гарах, бүртгүүлэх, OAuth callback, профайл засах) `WebRouter.php`-д `/session/` prefix-тэй байх ЁСТОЙ - бусад route-д session зөвхөн уншигдана.
- Амжилттай нэвтэрсний дараа шууд `session_regenerate_id(true)` дуудна (session fixation).
- Зочны сагс (`Cart::SESSION_KEY`) мөн энэ session-д байдаг тул нэвтрэхэд автоматаар хадгалагдана. Сагсыг бүртгэл тус бүрд DB-д хадгалдаг бол нэвтрэх үед зочны сагсыг хадгалсан сагстай нэгтгэнэ.

## 4. Олон нийтийн вэб дээрх CSRF

Одоо олон нийтийн формууд бүртгэлгүй тул spam token болон `SameSite=Lax` session cookie-д найддаг. Зочин нэвтэрсэн байх боломжтой болмогц бүртгэлийн төлөв өөрчилдөг route бүрд (профайл, нууц үг, хаяг, гарах, гишүүнээр захиалах) жинхэнэ CSRF token хэрэгтэй:

- нэвтрэх үед session бүрд token үүсгэж, нуугдмал талбар / meta tag-аар хэвлэнэ,
- controller дотор `hash_equals()`-ээр харьцуулна (эсвэл тэдгээр route-д вэб талын token key-тэй `Dashboard\CsrfMiddleware`-г ашиглана).

Гарах (logout) нь GET холбоос биш POST байна.

## 5. "Sign in with Google" (OpenID Connect)

Захиалагчийн "Gmail-ээр OAuth" гэдэг нь Gmail API биш, **Sign in with Google** юм. Зөвхөн `openid email profile` scope хүснэ. Gmail API-ийн scope нь Google-ийн хязгаарлагдмал scope-ийн баталгаажуулалтыг шаарддаг бөгөөд нэвтрэлтэд хэрэггүй.

Тохиргоо: Google Cloud Console -> APIs & Services -> Credentials -> OAuth client ID (Web application). Authorized redirect URI: `https://example.com/session/account/google/callback`. Google URI-г яг таг тулгадаг тул урсгал `/en/...` дээрээс эхэлсэн ч үргэлж default хэлний (prefix-гүй) callback URL-ийг илгээнэ; буцах хуудсыг `state`-ийн хамт session-д хадгална. Утгуудыг `.env`-д бичнэ:

```
RAPTOR_GOOGLE_CLIENT_ID=
RAPTOR_GOOGLE_CLIENT_SECRET=
```

`RAPTOR_GOOGLE_CLIENT_ID` хоосон бол Google товчийг нууна (функц унтарна).

Урсгал (authorization code + PKCE), нэмэлт package хэрэггүй:

1. `GET /session/account/google` - `state` болон PKCE `code_verifier` (санамсаргүй, 32+ byte) үүсгэж, хоёуланг session-д хадгалаад `https://accounts.google.com/o/oauth2/v2/auth` руу `response_type=code`, `client_id`, `redirect_uri`, `scope=openid email profile`, `state`, `code_challenge` (verifier-ийн SHA-256-ийн base64url), `code_challenge_method=S256` параметртэй redirect хийнэ.
2. `GET /session/account/google/callback` - `state` session-ий утгатай таарахгүй бол (`hash_equals`) татгалзаж, дараа нь session-оос устгана (нэг удаагийн).
3. `code` + `code_verifier` + client secret-ийг `https://oauth2.googleapis.com/token` руу POST хийнэ (`codesaur/http-client`).
4. Буцаж ирсэн `id_token`-ийг `firebase/php-jwt`-ээр (Raptor-ийн dependency-д аль хэдийн бий) `https://www.googleapis.com/oauth2/v3/certs`-ийн Google-ийн түлхүүрээр шалгана (`JWK::parseKeySet()`, cache хийнэ). `iss` нь `https://accounts.google.com` эсвэл `accounts.google.com`, `aud` нь өөрийн client id, `exp` ирээдүйд байгааг шалгана.
5. Бүртгэлийг имэйлээр биш **`sub`**-аар таньна. Имэйл хаяг өөрчлөгдөж, дахин ашиглагдаж болдог; `sub` өөрчлөгддөггүй.

Бүртгэл холбох дүрэм (бүртгэл булаах алдаа яг эндээс гардаг):

- `google_sub` олдвол -> тэр хэрэглэгчээр нэвтрүүлнэ.
- Олдоогүй, гэхдээ ижил имэйлтэй хэрэглэгч байвал -> token-д `email_verified: true` байгаа үед л холбоно; эс бөгөөс татгалзаж, эхлээд нууц үгээрээ нэвтрэхийг хүснэ.
- Огт олдоогүй -> `google_sub`, `email`, `name`, `email_verified_at = одоо`, `password = NULL`-тэй хэрэглэгч үүсгэнэ.

## 6. Гишүүний захиалга

Зочны захиалгыг хадгална. Заавал бүртгүүлэхийг шаардах нь худалдан авагч сагсаа орхих хамгийн түгээмэл шалтгаануудын нэг. Нэвтэрсэн хэрэглэгчийн хувьд:

- `/order` дээр нэр, имэйл, утас, хаягийг `customers` мөрөөс урьдчилан бөглөнө,
- `ShopController::orderSubmit()` дотор хэрэглэгч нэвтэрсэн бол захиалгад `customer_id`-г онооно (ID-г session-оос уншина; POST-оор ирсэн ID-д хэзээ ч итгэхгүй),
- `profile/orders` хуудсанд хэрэглэгчийн өөрийн захиалгыг `WHERE customer_id = :id`-аар жагсаана.

## 7. Нууц үгтэй бүртгэл

Google-ээс гадна имэйл + нууц үг санал болгох бол:

- `password_hash()` / `password_verify()`, доод урт, урт нууц үгийг зөвшөөрөх (дор хаяж 64 тэмдэгт),
- имэйл баталгаажуулах, нууц үг сэргээх холбоос нь хугацаатай, нэг удаагийн санамсаргүй token ашиглаж, hash хэлбэрээр хадгална; token-г устгахын оронд ашиглагдсан гэж тэмдэглэнэ (dashboard-ын `forgot` хүснэгттэй ижил загвар),
- нэвтрэх болон сэргээх хүсэлтэд rate limit тавина (dashboard-ын хувилбарыг `LoginController`-оос үз),
- "имэйл олдсонгүй" болон "нууц үг буруу" үед ижил мессеж харуулна.

## 8. Хувь хүний мэдээлэл

Хэрэглэгчийн бүртгэл бол хувь хүний мэдээлэл. Нууцлалын бодлогын хуудас, бүртгэлээ устгах боломж (идэвхгүй болгоод хувийн талбаруудыг арилгана; захиалгууд өөрт нь хадгалагдсан нэр/имэйлийн хуулбартайгаа үлдэнэ) өгч, нууц үг, token, OAuth-ийн бүтэн хариуг log-д бичихгүй.
