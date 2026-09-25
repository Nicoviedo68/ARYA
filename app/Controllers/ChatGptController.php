<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Models\ChatGpt;
use Arya\Models\Integration;
use Arya\Services\OpenAiChat;

final class ChatGptController extends Controller
{
    public function index(): void
    {
        $this->requireMenuAccess('chatgpt');
        ChatGpt::bootstrap();
        Integration::bootstrap();

        $user = auth();
        $userId = isset($user['id']) ? (int) $user['id'] : null;
        $chatId = (int) ($_GET['c'] ?? 0);
        $conversations = ChatGpt::listForUser($userId);
        $active = $chatId > 0 ? ChatGpt::find($chatId, $userId) : null;
        $messages = $active ? ChatGpt::messages((int) $active['id']) : [];
        $status = OpenAiChat::status();
        $showConfig = !$status['configured'] || isset($_GET['config']);

        $this->view('chatgpt/index', [
            'title'         => 'Chat GPT',
            'active'        => 'chatgpt',
            'conversations' => $conversations,
            'activeChat'    => $active,
            'messages'      => $messages,
            'models'        => OpenAiChat::models(),
            'defaultModel'  => OpenAiChat::DEFAULT_MODEL,
            'openaiStatus'  => $status,
            'showConfig'    => $showConfig,
            'userName'       => (string) ($user['name'] ?? 'tú'),
        ]);
    }

    public function saveConfig(): void
    {
        $this->requireMenuAccess('chatgpt');
        $this->validateCsrf();

        $endpoint = trim((string) ($_POST['endpoint_url'] ?? ''));
        $apiKey = trim((string) ($_POST['api_key'] ?? ''));
        $estado = strtolower(trim((string) ($_POST['estado'] ?? 'activo'))) ?: 'activo';

        $result = Integration::saveOpenAiConnection($endpoint, $apiKey, $estado);
        $status = OpenAiChat::status();

        if ($this->wantsJsonResponse()) {
            $this->json([
                'ok'           => !empty($result['ok']),
                'message'      => (string) ($result['message'] ?? 'No se pudo guardar.'),
                'openaiStatus' => $status,
            ], !empty($result['ok']) ? 200 : 422);
        }

        if (!empty($result['ok'])) {
            flash('success', 'OpenAI guardado. También visible en Configuración → Integraciones.');
            redirect('chat-gpt');
        }

        flash('error', (string) ($result['message'] ?? 'No se pudo guardar OpenAI.'));
        redirect('chat-gpt?config=1');
    }

    public function send(): void
    {
        $this->requireMenuAccess('chatgpt');
        $this->validateCsrf();
        ChatGpt::bootstrap();

        $user = auth();
        $userId = isset($user['id']) ? (int) $user['id'] : null;
        $prompt = trim((string) ($_POST['message'] ?? ''));
        $model = OpenAiChat::normalizeModel((string) ($_POST['model'] ?? OpenAiChat::DEFAULT_MODEL));
        $conversationId = (int) ($_POST['conversation_id'] ?? 0);

        if ($prompt === '') {
            $this->respondSend(['ok' => false, 'message' => 'Escribe un mensaje.'], 422);
        }

        if (mb_strlen($prompt) > 12000) {
            $this->respondSend(['ok' => false, 'message' => 'El mensaje es demasiado largo.'], 422);
        }

        $conv = $conversationId > 0 ? ChatGpt::find($conversationId, $userId) : null;
        if ($conv === null) {
            $title = mb_substr($prompt, 0, 60);
            $conversationId = (int) (ChatGpt::create($userId, $model, $title) ?? 0);
            if ($conversationId < 1) {
                $this->respondSend(['ok' => false, 'message' => 'No se pudo crear la conversación.'], 500);
            }
            $conv = ChatGpt::find($conversationId, $userId);
        } else {
            ChatGpt::setModel($conversationId, $model, $userId);
        }

        ChatGpt::addMessage($conversationId, 'user', $prompt);

        $history = ChatGpt::messages($conversationId);
        $apiMessages = array_map(static fn (array $m): array => [
            'role'    => (string) $m['role'],
            'content' => (string) $m['content'],
        ], $history);

        $result = OpenAiChat::chat($apiMessages, $model);
        if (empty($result['ok'])) {
            $this->respondSend([
                'ok'              => false,
                'message'         => (string) ($result['message'] ?? 'Error de OpenAI'),
                'conversation_id' => $conversationId,
                'messages'        => ChatGpt::messages($conversationId),
                'conversations'   => ChatGpt::listForUser($userId),
            ], 502);
        }

        $reply = (string) ($result['text'] ?? '');
        ChatGpt::addMessage($conversationId, 'assistant', $reply);

        // Título automático tras el primer intercambio
        $msgs = ChatGpt::messages($conversationId);
        if (count($msgs) <= 2 && $conv) {
            $autoTitle = mb_substr($prompt, 0, 60);
            if ($autoTitle !== '') {
                ChatGpt::rename($conversationId, $autoTitle, $userId);
            }
        }

        $this->respondSend([
            'ok'              => true,
            'message'         => 'OK',
            'conversation_id' => $conversationId,
            'reply'           => $reply,
            'model'           => $model,
            'messages'        => ChatGpt::messages($conversationId),
            'conversations'   => ChatGpt::listForUser($userId),
            'chat'            => ChatGpt::find($conversationId, $userId),
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function respondSend(array $payload, int $status = 200): void
    {
        if ($this->wantsJsonResponse()) {
            $this->json($payload, $status);
        }

        // Fallback producción: si el JS no corre, no perder el chat ni caer al dashboard.
        flash(empty($payload['ok']) ? 'error' : 'success', (string) ($payload['message'] ?? 'Listo'));
        $cid = (int) ($payload['conversation_id'] ?? 0);
        redirect($cid > 0 ? ('chat-gpt?c=' . $cid) : 'chat-gpt');
    }

    public function create(): void
    {
        $this->requireMenuAccess('chatgpt');
        $this->validateCsrf();
        ChatGpt::bootstrap();

        $user = auth();
        $userId = isset($user['id']) ? (int) $user['id'] : null;
        $model = OpenAiChat::normalizeModel((string) ($_POST['model'] ?? OpenAiChat::DEFAULT_MODEL));
        $id = ChatGpt::create($userId, $model, 'Nuevo chat');

        if ($id === null) {
            $this->json(['ok' => false, 'message' => 'No se pudo crear el chat.'], 500);
        }

        $this->json([
            'ok'              => true,
            'conversation_id' => $id,
            'chat'            => ChatGpt::find($id, $userId),
            'messages'        => [],
            'conversations'   => ChatGpt::listForUser($userId),
        ]);
    }

    public function show(string $id): void
    {
        $this->requireMenuAccess('chatgpt');
        ChatGpt::bootstrap();

        $user = auth();
        $userId = isset($user['id']) ? (int) $user['id'] : null;
        $chatId = (int) $id;
        $chat = ChatGpt::find($chatId, $userId);
        if ($chat === null) {
            $this->json(['ok' => false, 'message' => 'Chat no encontrado.'], 404);
        }

        $this->json([
            'ok'            => true,
            'chat'          => $chat,
            'messages'      => ChatGpt::messages($chatId),
            'conversations' => ChatGpt::listForUser($userId),
        ]);
    }

    public function rename(string $id): void
    {
        $this->requireMenuAccess('chatgpt');
        $this->validateCsrf();
        ChatGpt::bootstrap();

        $user = auth();
        $userId = isset($user['id']) ? (int) $user['id'] : null;
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $this->json(['ok' => false, 'message' => 'El nombre no puede estar vacío.'], 422);
        }

        $ok = ChatGpt::rename((int) $id, $title, $userId);
        $chat = $ok ? ChatGpt::find((int) $id, $userId) : null;

        $this->json([
            'ok'            => $ok,
            'message'       => $ok ? 'Nombre actualizado.' : 'No se pudo renombrar el chat.',
            'chat'          => $chat,
            'conversations' => ChatGpt::listForUser($userId),
        ], $ok ? 200 : 404);
    }

    public function delete(string $id): void
    {
        $this->requireMenuAccess('chatgpt');
        $this->validateCsrf();
        ChatGpt::bootstrap();

        $user = auth();
        $userId = isset($user['id']) ? (int) $user['id'] : null;
        $ok = ChatGpt::softDelete((int) $id, $userId);

        $this->json([
            'ok'            => $ok,
            'message'       => $ok ? 'Chat eliminado.' : 'No se pudo eliminar.',
            'conversations' => ChatGpt::listForUser($userId),
        ], $ok ? 200 : 404);
    }
}
