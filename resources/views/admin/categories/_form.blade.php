@if ($errors->any())
    <div class="alert alert-danger" role="alert">Revisa los campos marcados en rojo.</div>
@endif

<div class="card">
    <div class="field">
        <label for="name">Nombre</label>
        <input id="name" name="name" type="text" maxlength="60" required
               class="input @error('name') is-invalid @enderror"
               value="{{ old('name', $category->name) }}" placeholder="Ej: Destilados">
        @error('name') <div class="error">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-actions">
    <a href="{{ route('admin.categorias.index') }}" class="btn">Cancelar</a>
    <button type="submit" class="btn btn-primary">Guardar</button>
</div>
