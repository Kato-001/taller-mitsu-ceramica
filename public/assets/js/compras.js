/* =====================================================================
   Taller Mitsü · Registrar compra (CU15)
   Calcula el total mientras se escribe y sugiere el último costo promedio.
   El total real lo calcula la base de datos (columna generada + trigger).
   ===================================================================== */
(function () {
  'use strict';

  const form = document.getElementById('form-compra');
  const salida = document.getElementById('total-compra');
  if (!form || !salida) return;
  const bs = (n) => 'Bs ' + n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function recalcular() {
    let t = 0;
    form.querySelectorAll('.trat-row').forEach((fila) => {
      const c = parseFloat(fila.querySelector('[data-cantidad]').value) || 0;
      const p = parseFloat(fila.querySelector('[data-costo]').value) || 0;
      t += c * p;
    });
    salida.textContent = bs(Math.round(t * 100) / 100);
  }

  form.addEventListener('input', recalcular);
  form.addEventListener('click', () => setTimeout(recalcular, 0));   // al quitar filas
  form.addEventListener('change', (e) => {
    const sel = e.target.closest('[data-material]');
    if (!sel) return;
    const op = sel.selectedOptions[0];
    const costo = sel.closest('.trat-row').querySelector('[data-costo]');
    if (op && op.dataset.costo && !costo.value && parseFloat(op.dataset.costo) > 0) {
      costo.value = op.dataset.costo;   // sugerencia editable
      recalcular();
    }
  });
  recalcular();
})();
