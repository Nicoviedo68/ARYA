<?php
/** @var array|null $user */
/** @var array|null $profile */
$u = $profile ?: [];
$name = (string) ($u['nombre'] ?? $user['name'] ?? '');
$email = (string) ($u['correo'] ?? $user['email'] ?? '');
$usuario = (string) ($u['usuario'] ?? $user['usuario'] ?? '');
$alias = (string) ($u['alias'] ?? $user['role'] ?? '');
$telefono = (string) ($u['telefono'] ?? $user['telefono'] ?? '');
$imagen = (string) ($u['imagen'] ?? $user['avatar'] ?? '');
?>
<div class="profile-modal" id="profileModal" hidden>
  <div class="profile-modal-backdrop" data-profile-close></div>
  <div class="profile-modal-panel" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
    <header class="profile-modal-header">
      <div>
        <p class="profile-modal-eyebrow">maestrousuario</p>
        <h2 id="profileModalTitle">Ajustes de perfil</h2>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" data-profile-close aria-label="Cerrar">✕</button>
    </header>

    <form method="POST" action="<?= url('perfil/guardar') ?>" class="profile-modal-form" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label" for="pf_usuario">Usuario</label>
        <input class="form-control" type="text" id="pf_usuario" value="<?= e($usuario) ?>" disabled>
      </div>
      <div class="form-group">
        <label class="form-label" for="pf_alias">Alias</label>
        <input class="form-control" type="text" id="pf_alias" value="<?= e($alias) ?>" disabled>
      </div>
      <div class="form-group">
        <label class="form-label" for="pf_nombre">Nombre</label>
        <input class="form-control" type="text" id="pf_nombre" name="nombre" value="<?= e($name) ?>" required maxlength="150">
      </div>
      <div class="form-group">
        <label class="form-label" for="pf_correo">Correo</label>
        <input class="form-control" type="email" id="pf_correo" name="correo" value="<?= e($email) ?>" required maxlength="180">
      </div>
      <div class="form-group">
        <label class="form-label" for="pf_telefono">Teléfono</label>
        <input class="form-control" type="text" id="pf_telefono" name="telefono" value="<?= e($telefono) ?>" maxlength="40" placeholder="Opcional">
      </div>
      <div class="form-group">
        <label class="form-label" for="pf_imagen">Imagen (URL)</label>
        <input class="form-control" type="text" id="pf_imagen" name="imagen" value="<?= e($imagen) ?>" maxlength="500" placeholder="https://...">
      </div>
      <div class="form-group">
        <label class="form-label" for="pf_password">Nueva contraseña</label>
        <input class="form-control" type="password" id="pf_password" name="password" autocomplete="new-password" placeholder="Dejar vacío para no cambiar">
      </div>
      <div class="profile-modal-actions">
        <button type="button" class="btn btn-ghost" data-profile-close>Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar en DB</button>
      </div>
    </form>
  </div>
</div>
