<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

/**
 * Historial de conversaciones Chat GPT (OpenAI).
 */
final class ChatGpt
{
    public static function bootstrap(): void
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS arya_chatgpt_conversations (
                    id           BIGSERIAL PRIMARY KEY,
                    user_id      BIGINT,
                    title        VARCHAR(180) NOT NULL DEFAULT \'Nuevo chat\',
                    model        VARCHAR(80)  NOT NULL DEFAULT \'gpt-5.6\',
                    pinned       BOOLEAN NOT NULL DEFAULT FALSE,
                    activo       BOOLEAN NOT NULL DEFAULT TRUE,
                    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )'
            );
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS arya_chatgpt_messages (
                    id               BIGSERIAL PRIMARY KEY,
                    conversation_id  BIGINT NOT NULL REFERENCES arya_chatgpt_conversations(id) ON DELETE CASCADE,
                    role             VARCHAR(20) NOT NULL,
                    content          TEXT NOT NULL,
                    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_arya_cgpt_conv_user ON arya_chatgpt_conversations (user_id, updated_at DESC)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_arya_cgpt_msg_conv ON arya_chatgpt_messages (conversation_id, id ASC)');
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function listForUser(?int $userId, int $limit = 40): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }

        $lim = max(1, min(100, $limit));
        try {
            if ($userId === null) {
                $stmt = $pdo->query(
                    'SELECT id, title, model, pinned, created_at, updated_at
                     FROM arya_chatgpt_conversations
                     WHERE activo = TRUE
                     ORDER BY pinned DESC, updated_at DESC
                     LIMIT ' . $lim
                );
                return $stmt ? ($stmt->fetchAll() ?: []) : [];
            }

            $stmt = $pdo->prepare(
                'SELECT id, title, model, pinned, created_at, updated_at
                 FROM arya_chatgpt_conversations
                 WHERE activo = TRUE AND user_id = :uid
                 ORDER BY pinned DESC, updated_at DESC
                 LIMIT ' . $lim
            );
            $stmt->execute(['uid' => $userId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find(int $id, ?int $userId = null): ?array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $id < 1) {
            return null;
        }

        try {
            $sql = 'SELECT id, user_id, title, model, pinned, created_at, updated_at
                    FROM arya_chatgpt_conversations
                    WHERE id = :id AND activo = TRUE';
            $params = ['id' => $id];
            if ($userId !== null) {
                $sql .= ' AND user_id IS NOT DISTINCT FROM :uid';
                $params['uid'] = $userId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function create(?int $userId, string $model = 'gpt-5.6', string $title = 'Nuevo chat'): ?int
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO arya_chatgpt_conversations (user_id, title, model)
                 VALUES (:user_id, :title, :model)
                 RETURNING id'
            );
            $stmt->execute([
                'user_id' => $userId,
                'title'   => mb_substr(trim($title) !== '' ? trim($title) : 'Nuevo chat', 0, 180),
                'model'   => $model !== '' ? $model : 'gpt-5.6',
            ]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{id:int,role:string,content:string,created_at:string}>
     */
    public static function messages(int $conversationId): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $conversationId < 1) {
            return [];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, role, content, created_at
                 FROM arya_chatgpt_messages
                 WHERE conversation_id = :cid
                 ORDER BY id ASC'
            );
            $stmt->execute(['cid' => $conversationId]);
            $rows = $stmt->fetchAll() ?: [];
            return array_map(static function (array $r): array {
                return [
                    'id'         => (int) ($r['id'] ?? 0),
                    'role'       => (string) ($r['role'] ?? 'user'),
                    'content'    => (string) ($r['content'] ?? ''),
                    'created_at' => (string) ($r['created_at'] ?? ''),
                ];
            }, $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function addMessage(int $conversationId, string $role, string $content): ?int
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $conversationId < 1) {
            return null;
        }

        $role = in_array($role, ['user', 'assistant', 'system'], true) ? $role : 'user';
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO arya_chatgpt_messages (conversation_id, role, content)
                 VALUES (:cid, :role, :content)
                 RETURNING id'
            );
            $stmt->execute([
                'cid'     => $conversationId,
                'role'    => $role,
                'content' => $content,
            ]);
            $id = (int) $stmt->fetchColumn();

            $pdo->prepare(
                'UPDATE arya_chatgpt_conversations SET updated_at = NOW() WHERE id = :id'
            )->execute(['id' => $conversationId]);

            return $id;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function rename(int $id, string $title, ?int $userId = null): bool
    {
        $title = mb_substr(trim($title), 0, 180);
        if ($title === '' || self::find($id, $userId) === null) {
            return false;
        }

        $pdo = Database::connection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE arya_chatgpt_conversations
                 SET title = :title, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(['title' => $title, 'id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function setModel(int $id, string $model, ?int $userId = null): bool
    {
        if (self::find($id, $userId) === null) {
            return false;
        }
        $pdo = Database::connection();
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                'UPDATE arya_chatgpt_conversations SET model = :model, updated_at = NOW() WHERE id = :id'
            );
            $stmt->execute(['model' => $model, 'id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function softDelete(int $id, ?int $userId = null): bool
    {
        if (self::find($id, $userId) === null) {
            return false;
        }
        $pdo = Database::connection();
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                'UPDATE arya_chatgpt_conversations SET activo = FALSE, updated_at = NOW() WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
