<label>
    <span>Valor</span>
    <input type="number" name="precio" min="0.01" step="0.01" required>
</label>
<label>
    <span>Tipo de tarifa</span>
    <select name="tipo_tarifa" required>
        <option value="">Elegí una tarifa</option>
        <option value="visita">Por visita</option>
        <option value="por_hora">Por hora</option>
        <option value="turno">Por turno</option>
    </select>
</label>
<label>
    <span>Duración del turno</span>
    <div class="duration-input-group">
        <input type="number" name="duracion_valor" min="5" max="10080" step="5" placeholder="Ej.: 45" data-duration-value required>
        <select name="duracion_unidad" data-duration-unit aria-label="Unidad de duración" required>
            <option value="min">Minutos</option>
            <option value="hs">Horas</option>
        </select>
    </div>
</label>
