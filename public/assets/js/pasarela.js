/* =====================================================================
   Taller Mitsü · Pasarela de pago (CU21)
   Pestañas de método, vista previa de la tarjeta, formato de los campos
   y cuenta regresiva de la reserva. El servidor vuelve a validar todo.
   ===================================================================== */
(function () {
  'use strict';

  // Pestañas QR / Tarjeta / PayPal
  const botones = document.querySelectorAll('[data-metodo]');
  botones.forEach((b) => b.addEventListener('click', () => {
    botones.forEach((x) => x.setAttribute('aria-selected', String(x === b)));
    document.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== b.dataset.metodo; });
    history.replaceState(null, '', '?' + new URLSearchParams({ pedido: new URLSearchParams(location.search).get('pedido'), metodo: b.dataset.metodo }));
  }));

  // Tarjeta: número en grupos de 4, marca y vista previa
  const num = document.getElementById('tj-numero');
  const tit = document.getElementById('tj-titular');
  const ven = document.getElementById('tj-vence');
  const marca = (n) => (/^4/.test(n) ? 'VISA' : /^(5[1-5]|2[2-7])/.test(n) ? 'MASTERCARD' : /^3[47]/.test(n) ? 'AMEX' : 'TARJETA');
  if (num) {
    num.addEventListener('input', () => {
      const d = num.value.replace(/\D/g, '').slice(0, 19);
      num.value = d.replace(/(.{4})/g, '$1 ').trim();
      document.getElementById('tv-numero').textContent = (d + '•'.repeat(Math.max(0, 16 - d.length))).replace(/(.{4})/g, '$1 ').trim();
      document.getElementById('tv-marca').textContent = marca(d);
    });
    tit.addEventListener('input', () => { document.getElementById('tv-titular').textContent = tit.value.toUpperCase() || 'NOMBRE DEL TITULAR'; });
    ven.addEventListener('input', () => {
      let d = ven.value.replace(/\D/g, '').slice(0, 4);
      if (d.length > 2) d = d.slice(0, 2) + '/' + d.slice(2);
      ven.value = d;
      document.getElementById('tv-vence').textContent = d || 'MM/AA';
    });
    // Verificación de Luhn antes de enviar (el servidor la repite)
    document.getElementById('form-tarjeta').addEventListener('submit', (e) => {
      const d = num.value.replace(/\D/g, '');
      let s = 0;
      for (let i = d.length - 1, dob = false; i >= 0; i--, dob = !dob) { let x = +d[i]; if (dob && (x *= 2) > 9) x -= 9; s += x; }
      if (d.length < 13 || s % 10 !== 0) {
        e.preventDefault();
        if (window.mitsuToast) window.mitsuToast('error', 'El número de tarjeta no es válido.');
        num.focus();
      }
    });
  }

  // Cuenta regresiva de la reserva de las piezas
  const reloj = document.querySelector('.cronometro');
  if (reloj) {
    let s = parseInt(reloj.dataset.segundos, 10);
    const t = setInterval(() => {
      s = Math.max(0, s - 1);
      reloj.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
      if (s === 0) { clearInterval(t); if (window.mitsuToast) window.mitsuToast('error', 'Se venció el tiempo de reserva. Vuelve a armar tu carrito.'); }
    }, 1000);
  }
})();
