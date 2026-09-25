<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Models\Client;

final class ClientController extends Controller
{
    public function index(): void
    {
        $this->requireMenuAccess('clients');

        $filters = [
            'name'    => trim((string) ($_GET['nombre'] ?? $_GET['name'] ?? '')),
            'phone'   => trim((string) ($_GET['telefono'] ?? $_GET['phone'] ?? '')),
            'channel' => trim((string) ($_GET['canal'] ?? $_GET['channel'] ?? '')),
            'q'       => trim((string) ($_GET['q'] ?? '')),
        ];

        Client::syncIfNeeded(45);
        $clients = Client::search($filters);

        $this->view('clients/index', [
            'title'   => 'Gestión de clientes',
            'active'  => 'clients',
            'clients' => $clients,
            'filters' => $filters,
        ]);
    }

    public function store(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('clients');

        $result = Client::create([
            'name'            => $_POST['name'] ?? '',
            'email'           => $_POST['email'] ?? '',
            'phone'           => $_POST['phone'] ?? '',
            'company'         => $_POST['company'] ?? '',
            'channel'         => $_POST['channel'] ?? 'whatsapp',
            'status'          => $_POST['status'] ?? 'prospecto',
            'document_type'   => $_POST['document_type'] ?? '',
            'document_number' => $_POST['document_number'] ?? '',
            'address'         => $_POST['address'] ?? '',
            'city'            => $_POST['city'] ?? '',
            'notes'           => $_POST['notes'] ?? '',
        ]);

        flash($result['ok'] ? 'success' : 'error', $result['message']);

        if ($result['ok'] && !empty($result['id'])) {
            redirect('clientes/' . (int) $result['id']);
        }
        if (!$result['ok'] && !empty($result['id'])) {
            redirect('clientes/' . (int) $result['id']);
        }
        redirect('clientes?nuevo=1');
    }

    public function show(string $id): void
    {
        $this->requireMenuAccess('clients');

        Client::syncIfNeeded(45);
        $client = Client::find($id);
        if (!$client) {
            flash('error', 'Cliente no encontrado.');
            redirect('clientes');
        }

        $this->view('clients/show', [
            'title'    => 'Cliente · ' . $client['name'],
            'active'   => 'clients',
            'client'   => $client,
            'orders'   => [],
            'activity' => Client::inboundActivityByDay((int) $client['id'], 14),
        ]);
    }

    public function update(string $id): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('clients');

        $clientId = (int) $id;
        $result = Client::update($clientId, [
            'name'            => $_POST['name'] ?? '',
            'email'           => $_POST['email'] ?? '',
            'phone'           => $_POST['phone'] ?? '',
            'company'         => $_POST['company'] ?? '',
            'status'          => $_POST['status'] ?? 'prospecto',
            'document_type'   => $_POST['document_type'] ?? '',
            'document_number' => $_POST['document_number'] ?? '',
            'address'         => $_POST['address'] ?? '',
            'city'            => $_POST['city'] ?? '',
            'notes'           => $_POST['notes'] ?? '',
        ]);

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('clientes/' . $clientId);
    }
}
