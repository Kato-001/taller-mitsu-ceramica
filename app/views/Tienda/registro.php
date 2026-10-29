<?php
/**
 * Registro de clientes de la tienda
 * @var bool $google
 */
$titulo = 'Crear cuenta';
?>
<div class="auth-centro" style="padding:12px 0">
    <div class="card card-pad auth-tarjeta anim" style="max-width:640px">
        <h1 style="font-size:28px">Crea tu cuenta</h1>
        <p class="soft" style="margin:8px 0 20px">Con tu cuenta puedes comprar, ver tus pedidos y recibir tus comprobantes por correo.</p>
        <?= View::parcial('boton_google', ['google' => $google]) ?>
        <form method="post" action="/tienda/registro" class="stack" data-validar>
            <?= csrf_field() ?>
            <?= View::parcial('campos_persona', ['p' => null]) ?>
            <?= View::parcial('campos_clave', ['editando' => false]) ?>
            <label class="check" style="font-size:14px"><input type="checkbox" name="acepta" value="1" required>
                <span>Acepto que el taller use mis datos solo para procesar mis compras y enviarme los comprobantes.</span></label>
            <?= error_campo('acepta') ?>
            <button class="btn btn-primary btn-block" style="height:48px"><?= icono('usuario-mas', 17) ?> Crear mi cuenta</button>
        </form>
        <p class="faint" style="font-size:13.5px;margin:16px 0 0;text-align:center">¿Ya tienes cuenta? <a href="/tienda/ingresar">Ingresa</a> · ¿Quieres tomar clases? <a href="/registro">Cuenta de alumno</a></p>
    </div>
</div>
