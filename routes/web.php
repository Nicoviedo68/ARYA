<?php

declare(strict_types=1);

use Arya\Core\Router;

/** @var Router $router */

$router->get('/', 'AuthController@showLogin', 'home');
$router->get('/login', 'AuthController@showLogin', 'login', ['GuestMiddleware']);
$router->post('/login', 'AuthController@login', 'login.post', ['GuestMiddleware']);
$router->post('/login/ocp', 'AuthController@ocpChallenge', 'login.ocp', ['GuestMiddleware']);
$router->post('/login/ocp/canal', 'AuthController@ocpSendChannel', 'login.ocp.channel', ['GuestMiddleware']);
$router->post('/login/ocp/verificar', 'AuthController@ocpVerify', 'login.ocp.verify', ['GuestMiddleware']);
$router->post('/login/ocp/cancelar', 'AuthController@ocpCancel', 'login.ocp.cancel', ['GuestMiddleware']);
$router->post('/logout', 'AuthController@logout', 'logout', ['AuthMiddleware']);

// API para n8n (guardar cod_ingreso)
$router->post('/api/n8n/2fa/codigo', 'N8nController@saveCodigo', 'api.n8n.2fa.codigo');

// gen_redes — media público para Instagram / n8n (SIN auth)
$router->get('/gen-redes/media/{id}', 'GenRedesController@media', 'gen_redes.media');
$router->get('/gen-redes/media/{id}/url', 'GenRedesController@mediaUrl', 'gen_redes.media.url');
$router->post('/gen-redes/media', 'GenRedesController@store', 'gen_redes.media.store');

$router->get('/diagnostico', 'DiagnosticoController@index', 'diagnostico');
$router->post('/diagnostico', 'DiagnosticoController@index', 'diagnostico.post');

$router->get('/dashboard', 'DashboardController@index', 'dashboard', ['AuthMiddleware']);

$router->get('/omnicanalidad', 'OmnichannelController@index', 'omnichannel', ['AuthMiddleware']);
$router->get('/omnicanalidad/chat/{id}', 'OmnichannelController@show', 'omnichannel.show', ['AuthMiddleware']);
$router->get('/omnicanalidad/api/conversaciones', 'OmnichannelController@apiConversations', 'omnichannel.api.conversations', ['AuthMiddleware']);
$router->get('/omnicanalidad/api/mensajes/{id}', 'OmnichannelController@apiMessages', 'omnichannel.api.messages', ['AuthMiddleware']);
$router->get('/omnicanalidad/api/notificaciones', 'OmnichannelController@apiNotifications', 'omnichannel.api.notifications', ['AuthMiddleware']);
$router->get('/omnicanalidad/post-media/{id}', 'OmnichannelController@postMedia', 'omnichannel.postMedia', ['AuthMiddleware']);
$router->post('/omnicanalidad/enviar', 'OmnichannelController@send', 'omnichannel.send', ['AuthMiddleware']);
$router->post('/omnicanalidad/tomar', 'OmnichannelController@take', 'omnichannel.take', ['AuthMiddleware']);
$router->post('/omnicanalidad/cerrar', 'OmnichannelController@close', 'omnichannel.close', ['AuthMiddleware']);
$router->post('/omnicanalidad/ia/mejorar', 'OmnichannelController@improve', 'omnichannel.improve', ['AuthMiddleware']);

$router->get('/clientes', 'ClientController@index', 'clients', ['AuthMiddleware']);
$router->post('/clientes', 'ClientController@store', 'clients.store', ['AuthMiddleware']);
$router->get('/clientes/{id}', 'ClientController@show', 'clients.show', ['AuthMiddleware']);
$router->post('/clientes/{id}', 'ClientController@update', 'clients.update', ['AuthMiddleware']);

$router->get('/tareas', 'TaskController@index', 'tasks', ['AuthMiddleware']);
$router->get('/tareas/api', 'TaskController@apiList', 'tasks.api', ['AuthMiddleware']);
$router->post('/tareas/refresh', 'TaskController@refresh', 'tasks.refresh', ['AuthMiddleware']);
$router->post('/tareas/config', 'TaskController@saveConfig', 'tasks.config', ['AuthMiddleware']);
$router->post('/tareas/{id}/toggle', 'TaskController@toggle', 'tasks.toggle', ['AuthMiddleware']);

$router->get('/chat-gpt', 'ChatGptController@index', 'chatgpt', ['AuthMiddleware']);
$router->post('/chat-gpt/enviar', 'ChatGptController@send', 'chatgpt.send', ['AuthMiddleware']);
$router->post('/chat-gpt/nuevo', 'ChatGptController@create', 'chatgpt.create', ['AuthMiddleware']);
$router->post('/chat-gpt/config', 'ChatGptController@saveConfig', 'chatgpt.config', ['AuthMiddleware']);
$router->get('/chat-gpt/chat/{id}', 'ChatGptController@show', 'chatgpt.show', ['AuthMiddleware']);
$router->post('/chat-gpt/chat/{id}/renombrar', 'ChatGptController@rename', 'chatgpt.rename', ['AuthMiddleware']);
$router->post('/chat-gpt/chat/{id}/eliminar', 'ChatGptController@delete', 'chatgpt.delete', ['AuthMiddleware']);

$router->get('/generador', 'GeneratorController@index', 'generator', ['AuthMiddleware']);
$router->post('/generador/crear', 'GeneratorController@create', 'generator.create', ['AuthMiddleware']);

$router->get('/informes', 'ReportController@index', 'reports', ['AuthMiddleware']);
$router->get('/informes/exportar', 'ReportController@export', 'reports.export', ['AuthMiddleware']);

$router->get('/configuracion', 'SettingsController@index', 'settings', ['AuthMiddleware']);
$router->post('/configuracion/cuentas/guardar', 'SettingsController@saveAccount', 'settings.accounts.save', ['AuthMiddleware']);
$router->post('/configuracion/cuentas/eliminar', 'SettingsController@deleteAccount', 'settings.accounts.delete', ['AuthMiddleware']);
$router->post('/configuracion/meta/verify-rotar', 'SettingsController@rotateMetaVerify', 'settings.meta.verify', ['AuthMiddleware']);
$router->post('/configuracion/redes/guardar', 'SettingsController@saveRed', 'settings.redes.save', ['AuthMiddleware']);
$router->post('/configuracion/redes/eliminar', 'SettingsController@deleteRed', 'settings.redes.delete', ['AuthMiddleware']);
$router->post('/configuracion/integraciones/guardar', 'SettingsController@saveIntegration', 'settings.integrations.save', ['AuthMiddleware']);
$router->post('/configuracion/integraciones/eliminar', 'SettingsController@deleteIntegration', 'settings.integrations.delete', ['AuthMiddleware']);
$router->post('/perfil/guardar', 'SettingsController@saveProfile', 'profile.save', ['AuthMiddleware']);
