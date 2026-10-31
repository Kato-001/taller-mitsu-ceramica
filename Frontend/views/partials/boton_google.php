<?php
/**
 * Botón "Continuar con Google". Si aún no hay credenciales OAuth configuradas, se muestra deshabilitado.
 * @var bool $google
 */
?>
<?php if ($google): ?>
    <a href="/auth/google" class="btn btn-outline btn-block" style="height:48px;gap:10px">
        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.6 2.5 30.2 0 24 0 14.6 0 6.6 5.4 2.6 13.2l7.8 6.1C12.3 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.4c-.5 2.9-2.2 5.3-4.6 6.9l7.4 5.8c4.3-4 6.9-9.9 6.9-17.2z"/><path fill="#FBBC05" d="M10.4 28.7c-.5-1.4-.8-3-.8-4.7s.3-3.2.8-4.7l-7.8-6.1C1 16.5 0 20.1 0 24s1 7.5 2.6 10.8l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.4-5.8c-2.1 1.4-4.8 2.3-8.5 2.3-6.3 0-11.7-4.1-13.6-9.8l-7.8 6.1C6.6 42.6 14.6 48 24 48z"/></svg>
        Continuar con Google
    </a>
<?php else: ?>
    <button type="button" class="btn btn-outline btn-block" style="height:48px" disabled title="Se habilita al configurar las credenciales de Google">Continuar con Google (próximamente)</button>
<?php endif; ?>
<div class="row" style="gap:10px;margin:16px 0;color:var(--text-faint);font-size:13px"><span style="flex:1;height:1px;background:var(--border)"></span>o con tu correo<span style="flex:1;height:1px;background:var(--border)"></span></div>
