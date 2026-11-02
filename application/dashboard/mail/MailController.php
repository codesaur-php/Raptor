<?php

namespace Dashboard\Mail;

use Psr\Log\LogLevel;

use codesaur\Template\Markup;
use codesaur\Template\MemoryTemplate;

use Dashboard\Organization\OrganizationUserModel;
use Dashboard\RBAC\Roles;
use Dashboard\RBAC\UserRole;
use Dashboard\User\UsersModel;

/**
 * Class MailController
 *
 * Хэрэглэгчид рүү имэйл илгээх (Систем - Имэйл илгээх): сонгосон дүртэй бүх
 * хэрэглэгч ба/эсвэл сонгосон хэрэглэгчид. Үйлчилгээний мэдэгдэлд (засвар,
 * шинэ боломж, журам) зориулсан - маркетингийн рассылка биш.
 *
 *  - Эрх: system_mail_send (system_coder автоматаар).
 *  - Байгууллагын хүрээ: системийн байгууллагаар (id=1) нэвтэрсэн эсвэл
 *    system_coder бол бүх хэрэглэгч; бусад байгууллагаар нэвтэрсэн бол зөвхөн
 *    тэр байгууллагын хэрэглэгчид (organizationScope()).
 *  - Хүлээн авагчийг сервер дүр / хэрэглэгчийн ID-аас тогтооно - зөвхөн
 *    идэвхтэй, зөв имэйлтэй хэрэглэгч, давхардалгүй. Клиент хаяг илгээдэггүй.
 *  - Хүлээн авагч бүрт тусдаа имэйл (бусдын хаяг харагдахгүй), нэг илгээлтэд
 *    MAX_RECIPIENTS хүртэл. Хоёр алхамтай (send()): prepare - хүлээн авагчийг
 *    тогтоож id жагсаалт буцаана; batch - клиент тэр жагсаалтыг BATCH_SIZE-аар
 *    хувааж дараалсан хүсэлтээр илгээнэ (progress харагдана, timeout-д өртөхгүй).
 *
 * Security (English): system_mail_send required. Outside the system organization
 * the recipients are limited to the members of the user's current organization,
 * so a tenant admin can never email another tenant's users. Recipients are
 * resolved on the server from role / user ids - the client never supplies an
 * address.
 *
 * @package Dashboard\Mail
 */
class MailController extends \Dashboard\Controller
{
    use \Dashboard\Template\DashboardTrait;

    /** Нэг илгээлтийн хүлээн авагчийн дээд тоо. */
    public const MAX_RECIPIENTS = 1000;

    /**
     * Нэг batch хүсэлтэд илгээх имэйлийн дээд тоо. Нэг имэйл ~0.2-1 сек тул
     * багц ~4-20 сек - proxy / вэб серверийн 60 сек орчим timeout-д хүрэхгүй.
     */
    public const BATCH_SIZE = 20;

    /**
     * Имэйл илгээх хуудас.
     *
     * Route: GET /dashboard/mail-send
     *
     * @return void
     */
    public function index()
    {
        try {
            if (!$this->isUserCan('system_mail_send')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $orgId = $this->organizationScope();
            $users = (new UsersModel($this->pdo))->getName();
            $roles = (new Roles($this->pdo))->getName();
            $userRoles = (new UserRole($this->pdo))->getName();
            $scope = $this->scopeSql('u', $orgId);

            $roleStmt = $this->prepare(
                "SELECT r.id, r.alias, r.name, r.description, COUNT(DISTINCT u.id) AS recipients
                   FROM $roles r
                   LEFT JOIN $userRoles ur ON ur.role_id = r.id
                   LEFT JOIN $users u ON u.id = ur.user_id AND u.is_active = 1
                        AND u.email IS NOT NULL AND u.email <> ''$scope
                  GROUP BY r.id, r.alias, r.name, r.description
                  ORDER BY r.alias DESC, r.id"
            );
            $userStmt = $this->prepare(
                "SELECT u.id, u.username, u.first_name, u.last_name, u.email FROM $users u
                  WHERE u.is_active = 1 AND u.email IS NOT NULL AND u.email <> ''$scope
                  ORDER BY u.first_name, u.last_name"
            );
            if ($orgId !== null) {
                $roleStmt->bindValue(':org', $orgId, \PDO::PARAM_INT);
                $userStmt->bindValue(':org', $orgId, \PDO::PARAM_INT);
            }
            $roleRows = $roleStmt->execute() ? $roleStmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            $userRows = $userStmt->execute() ? $userStmt->fetchAll(\PDO::FETCH_ASSOC) : [];

            $dashboard = $this->dashboardTemplate(__DIR__ . '/mail-send.html', [
                'roles' => $roleRows,
                'users' => \array_map(fn($u) => [
                    'id' => (int) $u['id'],
                    'name' => \trim("{$u['first_name']} {$u['last_name']}") ?: $u['username'],
                    'email' => $u['email']
                ], $userRows),
                'scoped_organization' => $orgId === null ? null : ($this->getUser()->organization['name'] ?? ''),
                'max_recipients' => self::MAX_RECIPIENTS
            ]);
            $dashboard->set('title', $this->text('send-email'));
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'mail-index'];
            if (isset($err)) {
                $this->log(
                    'dashboard',
                    LogLevel::ERROR,
                    'Имэйл илгээх хуудсыг нээх үед алдаа гарлаа',
                    $context + ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]]
                );
            } else {
                $this->log('dashboard', LogLevel::NOTICE, 'Имэйл илгээх хуудсыг нээлээ', $context);
            }
        }
    }

    /**
     * Имэйл илгээх - хоёр алхамтай (mode).
     *
     * Route: POST /dashboard/mail-send/send
     *
     * 1) mode=prepare {subject, body, role_ids, user_ids}
     *    Хүлээн авагчийг тогтоож (идэвхтэй, зөв имэйлтэй, давхардалгүй, байгууллагын
     *    хүрээнд), тоо болон хэрэглэгчийн id жагсаалтыг (имэйл бүрт нэг) буцаана. Юу ч
     *    илгээхгүй. broadcast_id үүсгэж, бүтэн гарчиг, агуулгыг логт бичнэ.
     * 2) mode=batch {subject, body, user_ids (<= BATCH_SIZE), broadcast_id}
     *    Ирсэн id бүрийг серверт дахин шалгана - prepare-ээс хойш идэвхгүй болсон,
     *    устгагдсан, байгууллагаас хасагдсан, имэйлгүй болсон хэрэглэгч алгасагдана
     *    (skipped). Нэг имэйлийн алдаа (failed) бусдыг зогсоохгүй.
     *
     * Багц бүр богино (BATCH_SIZE имэйл) тул proxy / вэб серверийн timeout-д
     * өртөхгүй. Багцын хүсэлт бүхэлдээ амжилтгүй болбол клиент автоматаар дахин
     * илгээхгүй - сервер хэсгийг нь илгээсэн байж болох тул давхар имэйл очих эрсдэлтэй.
     *
     * Security (English): a batch never trusts the client's ids - every id is
     * re-resolved against users (active, valid email, organization scope), so a
     * forged or stale id is skipped, never emailed. No email address ever comes
     * from the client.
     *
     * @return void
     */
    public function send()
    {
        try {
            if (!$this->isUserCan('system_mail_send')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $payload = $this->getParsedBody();
            $mode = ($payload['mode'] ?? '') === 'prepare' ? 'prepare' : 'batch';
            $subject = \trim((string) ($payload['subject'] ?? ''));
            $text = \trim((string) ($payload['body'] ?? ''));
            if ($subject === '' || $text === '') {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $ids = fn($v) => \array_values(\array_unique(\array_filter(\array_map(
                'intval',
                \is_array($v) ? $v : \explode(',', (string) $v)
            ), fn($id) => $id > 0)));
            $orgId = $this->organizationScope();

            if ($mode === 'prepare') {
                $roleIds = $ids($payload['role_ids'] ?? []);
                $userIds = $ids($payload['user_ids'] ?? []);
                $recipients = $this->recipients($roleIds, $userIds, $orgId);
                if (empty($recipients)) {
                    throw new \InvalidArgumentException($this->text('no-recipients'), 400);
                }
                if (\count($recipients) > self::MAX_RECIPIENTS) {
                    throw new \InvalidArgumentException(
                        $this->text('too-many-recipients') . ' (' . \count($recipients) . ' > ' . self::MAX_RECIPIENTS . ')',
                        400
                    );
                }
                $broadcastId = \bin2hex(\random_bytes(8));
                $this->respondJSON([
                    'status' => 'success',
                    'broadcast_id' => $broadcastId,
                    'recipients' => \count($recipients),
                    'ids' => \array_column($recipients, 'id'),
                    'batch_size' => self::BATCH_SIZE
                ]);
                return;
            }

            // mode=batch
            $broadcastId = (string) ($payload['broadcast_id'] ?? '');
            if (!\preg_match('/^[a-f0-9]{16}$/', $broadcastId)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $userIds = $ids($payload['user_ids'] ?? []);
            if (empty($userIds) || \count($userIds) > self::BATCH_SIZE) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $recipients = $this->recipients([], $userIds, $orgId);
            $found = \array_column($recipients, 'id');
            $skipped = \array_values(\array_diff($userIds, $found));

            \ignore_user_abort(true);
            $html = $this->bodyHtml($subject, $text);
            $sent = 0;
            $failed = [];
            foreach ($recipients as $r) {
                $ok = false;
                try {
                    $ok = (new Mailer($this->pdo))->mail($r['email'], $r['name'], $subject, $html)->send();
                } catch (\Throwable) {
                    $ok = false;
                }
                if ($ok) {
                    $sent++;
                } else {
                    $failed[] = ['id' => $r['id'], 'email' => $r['email']];
                }
            }
            $this->respondJSON([
                'status' => 'success',
                'sent' => $sent,
                'failed' => $failed,
                'skipped' => $skipped
            ]);
        } catch (\Throwable $err) {
            $code = (int) $err->getCode();
            $this->respondJSON(['message' => $err->getMessage()], $code >= 400 && $code < 600 ? $code : 500);
        } finally {
            $error = isset($err) ? ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]] : [];
            if (($mode ?? 'batch') === 'prepare') {
                // Эхлэл: бүтэн агуулга нэг удаа - буруу ашиглалт (phishing, төөрөгдүүлэх
                // текст) гарвал хэн юу, хэдэн хүнд илгээхээр эхлүүлснийг нотлох аудит
                $this->log(
                    'dashboard',
                    isset($err) ? LogLevel::ERROR : LogLevel::NOTICE,
                    isset($err)
                        ? 'Имэйл илгээлт эхлүүлэх үед алдаа гарлаа - {error.message}'
                        : '"{subject}" имэйлийг {recipients} хүнд илгээж эхэллээ',
                    [
                        'action' => 'mail-broadcast-start',
                        'broadcast_id' => $broadcastId ?? null,
                        'subject' => $subject ?? null,
                        'body' => $text ?? null,
                        'body_sha1' => isset($text) ? \sha1($text) : null,
                        'role_ids' => $roleIds ?? [],
                        'user_ids' => $userIds ?? [],
                        'recipients' => isset($recipients) ? \count($recipients) : 0
                    ] + $error
                );
            } else {
                // Багц: агуулгын sha1 - эхлэлийн логтой тулгаж бүх багц ижил текст
                // илгээснийг шалгана
                $this->log(
                    'dashboard',
                    isset($err) ? LogLevel::ERROR : LogLevel::NOTICE,
                    isset($err)
                        ? 'Имэйлийн багц илгээх үед алдаа гарлаа - {error.message}'
                        : '"{subject}" имэйлийн багц: {sent} илгээсэн, {failed_count} алдаатай, {skipped_count} алгассан',
                    [
                        'action' => 'mail-broadcast',
                        'broadcast_id' => $broadcastId ?? null,
                        'subject' => $subject ?? null,
                        'body_sha1' => isset($text) ? \sha1($text) : null,
                        'user_ids' => $userIds ?? [],
                        'sent' => $sent ?? 0,
                        'failed' => $failed ?? [],
                        'failed_count' => isset($failed) ? \count($failed) : 0,
                        'skipped' => $skipped ?? [],
                        'skipped_count' => isset($skipped) ? \count($skipped) : 0
                    ] + $error
                );
            }
        }
    }

    /**
     * Хүлээн авагчийн байгууллагын хүрээ.
     *
     * system_coder эсвэл системийн байгууллагаар (id=1) нэвтэрсэн бол null (бүх
     * хэрэглэгч), бусад тохиолдолд одоогийн байгууллагын id - тэр байгууллагын
     * гишүүдээр хязгаарлана (BadgeController::isSystemWideViewer()-тэй адил дүрэм).
     *
     * Security (English): a non-system organization only reaches its own
     * members - keep this in every recipient query (index and send).
     *
     * @return int|null
     */
    private function organizationScope(): ?int
    {
        $orgId = (int) ($this->getUser()?->organization['id'] ?? 0);
        if ($this->isUser('system_coder') || $orgId === 1) {
            return null;
        }
        return $orgId;
    }

    /**
     * Хэрэглэгчийн хүснэгтийн alias-д байгууллагын хүрээний SQL нөхцөл (:org параметртэй).
     *
     * @param string   $alias Хэрэглэгчийн хүснэгтийн alias
     * @param int|null $orgId organizationScope() үр дүн
     * @return string Хоосон эсвэл " AND ..." нөхцөл
     */
    private function scopeSql(string $alias, ?int $orgId): string
    {
        if ($orgId === null) {
            return '';
        }
        $members = (new OrganizationUserModel($this->pdo))->getName();
        return " AND $alias.id IN (SELECT ou.user_id FROM $members ou WHERE ou.organization_id = :org)";
    }

    /**
     * Дүр ба хэрэглэгчийн ID-аас идэвхтэй, зөв имэйлтэй хэрэглэгчид (давхардалгүй),
     * байгууллагын хүрээнд.
     *
     * @param int[]    $roleIds
     * @param int[]    $userIds
     * @param int|null $orgId
     * @return array<int, array{id: int, email: string, name: string}>
     */
    private function recipients(array $roleIds, array $userIds, ?int $orgId): array
    {
        if (empty($roleIds) && empty($userIds)) {
            return [];
        }
        $users = (new UsersModel($this->pdo))->getName();
        $userRoles = (new UserRole($this->pdo))->getName();
        $where = [];
        // ID-ууд нь intval + > 0 шүүлтээр орсон бүхэл тоо
        if (!empty($roleIds)) {
            $where[] = "u.id IN (SELECT ur.user_id FROM $userRoles ur WHERE ur.role_id IN (" . \implode(',', $roleIds) . '))';
        }
        if (!empty($userIds)) {
            $where[] = 'u.id IN (' . \implode(',', $userIds) . ')';
        }
        $stmt = $this->prepare(
            "SELECT u.id, u.username, u.first_name, u.last_name, u.email FROM $users u
              WHERE u.is_active = 1 AND u.email IS NOT NULL AND u.email <> ''
                AND (" . \implode(' OR ', $where) . ')' . $this->scopeSql('u', $orgId)
        );
        if ($orgId !== null) {
            $stmt->bindValue(':org', $orgId, \PDO::PARAM_INT);
        }
        $result = [];
        if ($stmt->execute()) {
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $u) {
                $email = \strtolower(\trim((string) $u['email']));
                if (\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                    $result[$email] = [
                        'id' => (int) $u['id'],
                        'email' => $email,
                        'name' => \trim("{$u['first_name']} {$u['last_name']}") ?: (string) $u['username']
                    ];
                }
            }
        }
        return \array_values($result);
    }

    /**
     * Энгийн текстийг HTML имэйл болгоно: мөр таслал хадгална, холбоосыг идэвхжүүлнэ,
     * доор нь вэб сайтын нэр (Тохируулгууд - title) бичнэ.
     *
     * @param string $subject
     * @param string $text
     * @return string
     */
    private function bodyHtml(string $subject, string $text): string
    {
        $html = \nl2br(\htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
        $html = \preg_replace('#(https?://[^\s<]+)#u', '<a href="$1">$1</a>', $html);
        $settings = $this->getAttribute('settings', []);
        $template = new MemoryTemplate();
        $template->set('subject', $subject);
        $template->set('content', new Markup($html));
        $template->set('site', (string) ($settings['title'] ?? ''));
        $template->source(
            '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#212529;max-width:640px">'
            . '<h3 style="color:#0d6efd;margin:0 0 12px">{{ subject }}</h3>'
            . '<div>{{ content }}</div>'
            . '{% if site %}<hr style="border:0;border-top:1px solid #dee2e6;margin:20px 0 8px">'
            . '<div style="font-size:12px;color:#6c757d">{{ site }}</div>{% endif %}'
            . '</div>'
        );
        return $template->output();
    }
}
