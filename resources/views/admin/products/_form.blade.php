@if ($errors->any())
    <div class="alert alert-danger" role="alert">Revisa los campos marcados en rojo.</div>
@endif

<div class="card">
    <div class="field">
        <label for="name">Nombre</label>
        <input id="name" name="name" type="text" maxlength="150" required
               class="input @error('name') is-invalid @enderror"
               value="{{ old('name', $product->name) }}">
        @error('name') <div class="error">{{ $message }}</div> @enderror
    </div>

    <div class="field">
        <label for="category_id">Categoría</label>
        <select id="category_id" name="category_id" required class="input @error('category_id') is-invalid @enderror">
            <option value="">Elige una…</option>
            @foreach ($categories as $id => $label)
                <option value="{{ $id }}" @selected((string) old('category_id', $product->category_id) === (string) $id)>{{ $label }}</option>
            @endforeach
            <option value="{{ \App\Http\Requests\Admin\ProductRequest::NEW_CATEGORY }}"
                    @selected(old('category_id') === \App\Http\Requests\Admin\ProductRequest::NEW_CATEGORY)>➕ Crear nueva categoría…</option>
        </select>
        @error('category_id') <div class="error">{{ $message }}</div> @enderror
    </div>

    <div class="field" data-new-category
         @if (old('category_id') !== \App\Http\Requests\Admin\ProductRequest::NEW_CATEGORY) hidden @endif>
        <label for="new_category">Nombre de la nueva categoría</label>
        <input id="new_category" name="new_category" type="text" maxlength="60"
               class="input @error('new_category') is-invalid @enderror"
               value="{{ old('new_category') }}" placeholder="Ej: Cervezas">
        <div class="hint">Se creará al guardar el producto. Luego la puedes editar en «Categorías».</div>
        @error('new_category') <div class="error">{{ $message }}</div> @enderror
    </div>

    <div class="field">
        <label for="description">Descripción <span class="muted small">(opcional)</span></label>
        <textarea id="description" name="description" maxlength="2000"
                  class="input @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
        @error('description') <div class="error">{{ $message }}</div> @enderror
    </div>

    <div class="field">
        <label for="photo">Foto <span class="muted small">(opcional)</span></label>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
               class="input @error('photo') is-invalid @enderror" data-photo-input="photo-preview">
        <div class="hint">JPG, PNG o WEBP. Máximo 4 MB.</div>
        @error('photo') <div class="error">{{ $message }}</div> @enderror
        <img id="photo-preview" class="photo-preview" alt="Vista previa de la foto"
             src="{{ $product->photoUrl() ?? '' }}" @if (! $product->photo_path) hidden @endif>
        @if ($product->photo_path)
            <label class="check small"><input type="checkbox" name="remove_photo" value="1"> Quitar la foto actual</label>
        @endif
    </div>
</div>

<div class="card">
    <h2>Precio e inventario</h2>
    <div class="grid-2">
        <div class="field">
            @if ($product->exists)
                <label for="cost">Costo promedio en bodega</label>
                <div class="input-money">
                    <input id="cost" name="cost" type="text" class="input" value="{{ $product->cost }}" disabled>
                </div>
                <div class="hint">Se calcula solo con los costos de tus compras. No se edita aquí.</div>
            @else
                <label for="cost">Costo de referencia (lo que te cuesta a ti)</label>
                <div class="input-money">
                    <input id="cost" name="cost" type="text" inputmode="numeric" autocomplete="off" required
                           class="input @error('cost') is-invalid @enderror"
                           value="{{ old('cost', $product->cost) }}">
                </div>
                <div class="hint" data-money-preview="cost"></div>
                <div class="hint">Sirve para calcular la ganancia y el precio. El costo real lo toma de cada compra.</div>
                @error('cost') <div class="error">{{ $message }}</div> @enderror
            @endif
        </div>

        <div class="field">
            <label for="price">Precio de venta</label>
            <div class="input-money">
                <input id="price" name="price" type="text" inputmode="numeric" autocomplete="off" required
                       class="input @error('price') is-invalid @enderror"
                       value="{{ old('price', $product->price) }}">
            </div>
            <div class="hint" data-money-preview="price"></div>
            @error('price') <div class="error">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="alert alert-warning" data-price-warning hidden>
        Atención: el precio de venta es menor que el costo. Venderías con pérdida.
    </div>

    <div class="calc-box" aria-live="polite">
        <div class="calc-grid">
            <div class="calc-item"><span class="k">Ganancia por unidad</span><span class="v" data-out="profit">—</span></div>
            <div class="calc-item"><span class="k">Margen sobre el precio</span><span class="v" data-out="margin">—</span></div>
            <div class="calc-item"><span class="k">Recargo sobre el costo</span><span class="v" data-out="markup">—</span></div>
            @if ($product->exists)
                <div class="calc-item"><span class="k">Ganancia de todo el stock</span><span class="v" data-out="stockProfit">—</span></div>
            @endif
        </div>

        <div class="reverse-calc">
            <div class="field">
                <label for="target-margin">Margen que quiero (%)</label>
                <input id="target-margin" type="text" inputmode="decimal" class="input" placeholder="Ej: 30" data-target-margin>
                <div class="error" data-margin-error hidden>El margen debe estar entre 0 y 99,9.</div>
            </div>
            <div>
                <span class="k small muted">Precio sugerido</span><br>
                <strong data-suggested-price>—</strong>
            </div>
            <button type="button" class="btn" data-use-price disabled>Usar este precio</button>
        </div>
    </div>

    <div class="field">
        @if ($product->exists)
            <label for="stock">Stock (unidades disponibles)</label>
            <input id="stock" name="stock" type="text" class="input" value="{{ $product->stock }}" disabled>
            <div class="hint">
                El stock no se edita aquí: sube con <a href="{{ route('admin.compras.create', ['producto' => $product->id]) }}">Compras</a>,
                baja con Ventas, y para daños, pérdidas o correcciones usa
                <a href="{{ route('admin.ajustes-inventario.create', $product) }}">Ajustar inventario</a>.
            </div>
        @else
            <div class="alert alert-info">
                📦 El producto se crea sin unidades. Al guardarlo te llevamos a <strong>Registrar compra</strong>
                para agregarle el stock.
            </div>
        @endif
    </div>

    <div class="field">
        <input type="hidden" name="is_visible" value="0">
        <label class="check">
            <input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', $product->is_visible))>
            Mostrar en el catálogo
        </label>
    </div>
</div>

<div class="form-actions">
    <a href="{{ route('admin.productos.index') }}" class="btn">Cancelar</a>
    <button type="submit" class="btn btn-primary">Guardar</button>
</div>
