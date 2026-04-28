<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

/**
 * Class TextController
 *
 * Нутагшуулалтын (Localization) системийн орчуулгын текстүүдийг
 * үүсгэх, үзэх, засварлах болон устгах CRUD ажиллагааг
 * хариуцдаг Controller класс.
 */
class TextController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Орчуулгын текстийн шинэ бичлэг үүсгэх (INSERT).
     *
     * POST хүсэлтээр бичлэг хадгалж JSON хариу буцаана.
     * GET хүсэлтээр оруулах форм бүхий template рендерлэнэ.
     *
     * @return void
     * @throws \Exception Эрх хүрэлцэхгүй эсвэл алдаа гарвал
     */
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_localization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            if ($this->getRequest()->getMethod() == 'POST') {
                $payload = [];
                $content = [];
                $parsedBody = $this->getParsedBody();
                foreach ($parsedBody as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                        }
                    } else {
                        $payload[$index] = $value;
                    }
                }

                if (empty($payload['keyword'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                $model = new TextModel($this->pdo);
                $found = $this->findByKeyword($model, $parsedBody['keyword']);
                if (!empty($found)) {
                    throw new \Exception(
                        $this->text('keyword-existing-in') . ' -> ID = ' . $found['id']
                    );
                }

                $record = $model->insert(
                    $payload + ['created_by' => $this->getUserId()], $content
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $this->invalidateCache('texts.{code}');
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);

                $this->dispatch(new \Raptor\Notification\ContentEvent(
                    'insert', 'text', $payload['keyword'] ?? '', null
                ));
            } else {
                $this->template(
                    __DIR__ . '/text-insert-modal.html'
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'localization-text-create'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Текст үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = '[{record.keyword}] текст амжилттай үүслээ';
                $context += ['record_id' => $record['id'], 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Текст үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->log('content', $level, $message, $context);
        }
    }

    /**
     * Орчуулгын текстийн мэдээллийг харах (VIEW).
     *
     * @param int $id Тухайн текстийн id дугаар.
     */
    public function view(int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new TextModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->template(
                __DIR__ . '/text-retrieve-modal.html',
                ['record' => $record]
            )->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = [
                'action' => 'localization-text-view',
                'id' => $id
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай текст мэдээллийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '[{record.keyword}] текст мэдээллийг үзэж байна';
                $context += ['record' => $record];
            }
            $this->log('content', $level, $message, $context);
        }
    }

    /**
     * Орчуулгын текстийн бичлэгийг засварлах (UPDATE).
     *
     * @param int $id Засварлах гэж буй бичлэгийн id дугаар.
     */
    public function update(int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $model = new TextModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            if ($this->getRequest()->getMethod() == 'PUT') {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody)) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload = [];
                $content = [];
                $updates = [];
                foreach ($parsedBody as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                            if ($record['localized'][$key][$index] != $value) {
                                $updates[] = "{$index}_{$key}";
                            }
                        }
                    } else {
                        $payload[$index] = $value;
                        if ($record[$index] != $value) {
                            $updates[] = $index;
                        }
                    }
                }
                if (empty($updates)) {
                    throw new \InvalidArgumentException('No update!');
                }
                if (empty($payload['keyword'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $found = $this->findByKeyword($model, $parsedBody['keyword']);
                if (!empty($found) && $found['id'] != $id) {
                    throw new \Exception(
                        $this->text('keyword-existing-in') . ' -> ID = ' . $found['id']
                    );
                }
                $updated = $model->updateById(
                    $id, $payload + ['updated_by' => $this->getUserId()], $content
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                $this->invalidateCache('texts.{code}');
                $this->respondJSON([
                    'type' => 'primary',
                    'status' => 'success',
                    'message' => $this->text('record-update-success')
                ]);

                $this->dispatch(new \Raptor\Notification\ContentEvent(
                    'update', 'text', $record['keyword'] ?? '', $id
                ));
            } else {
                $this->template(
                    __DIR__ . '/text-update-modal.html',
                    ['record' => $record]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = [
                'action' => 'localization-text-update',
                'record_id' => $id
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай текст мэдээллийг өөрчлөх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '[{record.keyword}] текст мэдээллийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '[{record.keyword}] текст мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record];
            }
            $this->log('content', $level, $message, $context);
        }
    }

    /**
     * Орчуулгын текст мэдээллийг устгах.
     *
     * DELETE хүсэлтээр устгаж JSON хариу буцаана.
     *
     * @return void
     * @throws \Exception Эрх хүрэлцэхгүй эсвэл алдаа гарвал
     */
    public function delete()
    {
        try {
            if (!$this->isUserCan('system_localization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $model = new TextModel($this->pdo);
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $record = $model->getById($id);
            $model->deleteById($id);
            if (!empty($record)) {
                (new \Raptor\Trash\TrashModel($this->pdo))->store(
                    'content', $model->getName(), $id, $record, $this->getUserId()
                );
            }
            $this->invalidateCache('texts.{code}');
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);

            $this->dispatch(new \Raptor\Notification\ContentEvent(
                'delete', 'text', $payload['keyword'] ?? "#{$id}", $id ?? null
            ));
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'localization-text-delete'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Текст мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record_id} дугаартай [{server_request.body.keyword}] текст мэдээллийг устгалаа';
                $context += ['record_id' => $id];
            }
            $this->log('content', $level, $message, $context);
        }
    }

    /**
     * Keyword давхцаж буй эсэхийг шалгана.
     *
     * @param TextModel $model
     * @param string $keyword
     * @return array
     */
    private function findByKeyword(TextModel $model, string $keyword): array|false
    {
        $table = $model->getName();
        $select = $this->prepare("SELECT * FROM $table WHERE keyword=:1 LIMIT 1");
        $select->bindParam(':1', $keyword);
        if ($select->execute() && $select->rowCount() == 1) {
            return $select->fetch();
        }
        return false;
    }
}
