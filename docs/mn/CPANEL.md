# cPanel Git Version Control deploy (SSH-гүй хостинд)

Ийм орчны нэг бодит жишээ нь Монгол Улсын Үндэсний Дата Төвийн төрийн
байгууллагуудын веб порталд зориулсан shared hosting юм.

> Raptor-ийн үндсэн deploy аргууд (FTP, SSH, Windows self-hosted runner) нь
> `.github/workflows/deploy.yml` дотор бичигдсэн - тохируулах заавар нь тухайн
> файлын толгойн тайлбарт бий. Энэ баримт нь зөвхөн тэдгээрийн аль нь ч сервер
> лүү хүрч чадахгүй онцгой орчны (SSH/Terminal-гүй cPanel shared hosting)
> fallback scaffold-ийг тайлбарлана.

cPanel Git Version Control дээр SSH/Terminal-гүй орчинд `git push` -> live
автомат deploy хийх темплейт файлууд `docs/conf.example/` дотор байгаа.
Эдгээр нь application код биш, deploy scaffold-ийн темплейт - шинэ төсөл
болгон өөрийн зам/хостоор тохируулж авна.

Файлууд:

- `docs/conf.example/.cpanel.yml.example` - repo root-д `.cpanel.yml` нэрээр
  хуулна. cPanel UI дээр "Deploy HEAD Commit" дарахад ажиллах task list.
- `docs/conf.example/auto-deploy.sh.example` - `deploy/auto-deploy.sh` болгож
  хуулна. Cron-оор N минут тутам ажиллаж, `.cpanel.yml`-ийн алхмуудыг гараар
  давтана (Deploy товч даралгүйгээр бүрэн автомат).

## Хэзээ хэрэглэх

- Хостинг cPanel Git VCS-тэй, git repo серверт pull хийгддэг.
- rsync байхгүй (CloudLinux CageFS) -> `cp` overlay ашиглана.
- `vendor/` git-д ороогүй (gitignore) -> autoload-ыг серверт composer-оор
  regenerate хийх ёстой.

## Placeholder-ууд (заавал тохируулна)

| Placeholder | Утга |
|-------------|------|
| `USERNAME` | cPanel хэрэглэгчийн нэр (home directory) |
| `PROJECT` | Repository нэр (cPanel Git "Repository Path" доторх) |
| `BRANCH` | Deploy хийх branch (default `main`) |
| `/opt/cpanel/composer/bin/composer` | Хостын composer binary зам |
| `/opt/cpanel/ea-php*/root/usr/bin/php` | CLI php binary хайх glob (хост тус бүр өөр) |

Хэрэв хост cPanel биш (Plesk, шууд VPS г.м.) бол зам/бинариудыг тохируулж,
CLI php-г шууд зааж болно. rsync байвал `cp -a` оронд `rsync -a --delete`
ашиглаж болно.

Cron тохиргоо (cPanel -> Cron Jobs, N=2-5 минут):

```
*/5 * * * * /bin/bash /home/USERNAME/repositories/PROJECT/deploy/auto-deploy.sh
```

## Developer заавар (заавал уншина - алдаанаас сэргийлэх)

Доорх дүрмүүд нь бодит deploy алдаануудаас цугларсан ("Class not found" гинжин
алдаа шинэ модуль/namespace нэмэхэд).

1. **vendor git-д ороогүй** тул `composer.json`-ий `autoload` (PSR-4 map)
   өөрчилвөл серверт autoload заавал regenerate болох ёстой. Scaffold нь үүнийг
   `composer.json` өөрчлөгдсөнийг мэдэж `composer dump-autoload -o` ажиллуулж
   шийднэ. Хэрэв scaffold-гүй/composer серверт ажиллахгүй бол дараагийн дүрмийг
   бариар.

2. **Фолдер нэрлэх дүрэм (composer ажиллахгүй үеийн найдвартай хувилбар):**
   Шинэ модулийн фолдерын нэрийг namespace-ийн сегменттэй яг адил (PascalCase)
   нэрлэвэл composer огт хэрэггүйгээр ажиллана. Учир: суурийн PSR-4 prefix
   (жишээ `"Dashboard\\": "application/dashboard/"`) нь `Dashboard\Foo\Bar`-ыг
   шууд `application/dashboard/Foo/Bar.php` руу зурна (Linux дээр том/жижиг үсэг
   ялгаатай). Харин lowercase фолдер (`application/dashboard/foo`) нь зөвхөн
   explicit map (`"Dashboard\\Foo\\": "application/dashboard/foo"`)-аар олдох тул
   `composer dump-autoload` заавал шаардана. Товчоор:
   - Composer серверт найдвартай ажилладаг -> lowercase болно.
   - Composer серверт ажилладаггүй/эргэлзээтэй -> PascalCase (фолдер = namespace).

3. **CLI SAPI php gotcha:** cron-ийн PATH дахь `php` нь ихэвчлэн CGI/FPM SAPI
   байдаг ба composer "cannot be run safely on non-CLI SAPIs" гэж унадаг. Тиймээс
   scaffold нь `PHP_SAPI == 'cli'` буцаадаг php binary-г тусад нь хайж
   (`ls /opt/cpanel/ea-php*/.../php`) ашиглана. `.cpanel.yml` (UI deploy) нь
   ихэвчлэн CLI орчинд ажилладаг тул тэнд шаардлагагүй.

4. **Хамгийн чухал - deploy script-ийг өөрийг нь өөрчлөх дараалал:**
   `auto-deploy.sh` нь `main()`-г бүтнээр уншаад дараа нь `git reset` хийдэг тул
   script-ийг шинэчлэх commit-ийг deploy хийхэд тэр deploy хуучин логикоороо
   ажиллана. Тиймээс deploy зан үйлээс хамаарах өөрчлөлтийг script засвартай
   нэг commit-д бүү хий. 2 үе шаттай хий:
   - Үе 1: зөвхөн deploy script засвар (кодын ажиллагаа хэвээр, юу ч эвдрэхгүй).
     -> deploy болгож, серверт шинэ script суусныг баталгаажуул.
   - Үе 2: script-ийн шинэ зан үйлээс хамаарах өөрчлөлт (жишээ lowercase rename).
     -> одоо сервер дээрх шинэ script үүнийг зөв боловсруулна.

5. **Баталгаажуулалт:** deploy-ийн дараа `LOG` (`.../logs/auto-deploy.log`)-д
   `deploy дуусав (<hash>)` болон composer ажилласан бол
   `composer.json -> composer dump-autoload -o (...)` мөр гарсан эсэхийг шалга.
   `Анхаар: CLI PHP олдсонгүй` гарвал autoload шинэчлэгдээгүй -> class олдохгүй.

6. **Сүлжээний тасралтын лог:** GitHub-д холбогдож чадахгүй үед cron бүр
   алдаа бичээд лог томордоггүй - script нь OFFLINE тэмдэг файлаар
   (`/tmp/PROJECT-auto-deploy.offline`) төлөв хадгалж, тасралт эхлэхэд
   `git fetch амжилтгүй...` гэсэн 1 мөр, холболт сэргэхэд `git fetch сэргэв...`
   гэсэн 1 мөр л бичнэ. Хоёр мөрийн хооронд чимээгүй байсан хугацаа нь
   тасралтын үргэлжилсэн хугацаа юм.
