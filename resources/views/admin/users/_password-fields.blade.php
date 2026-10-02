<div class="field">
    <label for="password">Contraseña nueva</label>
    <input id="password" name="password" type="password" autocomplete="new-password"
           class="input @error('password') is-invalid @enderror">
    <div class="hint">Mínimo 10 caracteres, con letras y números. Ej: <em>Mestra.Santos2026</em></div>
    @error('password') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="password_confirmation">Repite la contraseña nueva</label>
    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="input">
</div>

<div class="field">
    <label for="current_password">Tu contraseña actual <span class="muted small">(para confirmar que eres tú)</span></label>
    <input id="current_password" name="current_password" type="password" autocomplete="current-password"
           class="input @error('current_password') is-invalid @enderror">
    @error('current_password') <div class="error">{{ $message }}</div> @enderror
</div>
