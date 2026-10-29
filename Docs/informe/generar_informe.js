/**
 * Genera el informe final (Word, APA 7) del Sistema Mitsü: ciclos I, II y III.
 *
 *   NODE_PATH=<carpeta con docx> node Docs/informe/generar_informe.js
 *
 * Usa: datos/casos.json (casos de uso), datos/bd.json (resultados reales de la base),
 * datos/esquema.json (tablas reales), ../diagramas/png y capturas/.
 */
const fs = require('fs');
const path = require('path');
const W = require('./word');
const { d, H1, H1s, H2, H3, H4, P, L, salto, vacio, figura, tabla, ficha, codigo, resultado, ref, horizontal } = W;

const AQUI = __dirname;
const RAIZ = path.join(AQUI, '..', '..');
const DIAG = f => path.join(AQUI, '..', 'diagramas', 'png', f + '.png');
const CAP = f => path.join(AQUI, 'capturas', f + '.png');
const REC = f => path.join(AQUI, 'recursos', f);
const leer = f => JSON.parse(fs.readFileSync(path.join(AQUI, 'datos', f), 'utf8'));
const { casos: CASOS, actores: ACTORES, paquetes: PAQUETES } = leer('casos.json');
const BD = leer('bd.json');
const ESQUEMA = leer('esquema.json').tablas;
const SQL = f => fs.readFileSync(path.join(RAIZ, 'Bakend', 'database', f), 'utf8');

const REPO = 'https://github.com/Kato-001/taller-mitsu-ceramica';
const SITIO = 'https://taller-mitsu-ceramica.vercel.app';
const INTEGRANTES = ['Aquise Huanca Camila', 'Baqueros Hurtado Marco Antonio Junior', 'Estaca Paco Sergio', 'Miranda Soria Mauricio', 'Santeyana Durán Sarah Nicol'];
const CICLOS = [['I', 'Ciclo I: seguridad y configuración'], ['II', 'Ciclo II: clases y reservas'], ['III', 'Ciclo III: inventario, ventas y tienda en línea']];
const deCiclo = c => CASOS.filter(x => x.ciclo === c);
const NOTA_FUENTE = 'Elaboración propia.';

// ---------------------------------------------------------------------------
// Secciones (para alternar páginas verticales y horizontales)
// ---------------------------------------------------------------------------
const secciones = [];
let actual = null;
const encabezado = () => ({
    default: new d.Header({ children: [new d.Paragraph({ alignment: d.AlignmentType.RIGHT, children: [new d.TextRun({ children: [d.PageNumber.CURRENT], font: W.FUENTE })] })] }),
});
function seccion(apaisada = false, sinNumero = false) {
    horizontal(apaisada);
    actual = {
        properties: {
            type: secciones.length ? d.SectionType.NEXT_PAGE : undefined,
            page: {
                size: { width: 12240, height: 15840, orientation: apaisada ? d.PageOrientation.LANDSCAPE : d.PageOrientation.PORTRAIT },
                margin: { top: 1440, bottom: 1440, left: 1440, right: 1440 },
            },
        },
        headers: sinNumero ? undefined : encabezado(),
        children: [],
    };
    secciones.push(actual);
}
const add = (...xs) => xs.flat().forEach(x => actual.children.push(x));

// ===========================================================================
// CARÁTULA Y QR
// ===========================================================================
seccion(false, true);
const centro = (t, op = {}) => new d.Paragraph({ alignment: d.AlignmentType.CENTER, spacing: { after: op.despues ?? 160 }, children: [new d.TextRun({ text: t, font: W.FUENTE, bold: op.negrita, size: op.tam || 24, color: op.color })] });
add(
    centro('UNIVERSIDAD AUTÓNOMA GABRIEL RENÉ MORENO', { negrita: true, tam: 30 }),
    centro('FACULTAD DE INGENIERÍA EN CIENCIAS DE LA COMPUTACIÓN Y TELECOMUNICACIONES', { negrita: true, tam: 24, color: '1F4E79' }),
    new d.Paragraph({ alignment: d.AlignmentType.CENTER, spacing: { before: 200, after: 200 }, children: [W.imagen(REC('logo_ficct.png'), 170, 220)] }),
    centro('SISTEMA DE INFORMACIÓN PARA LA GESTIÓN INTEGRAL DEL TALLER DE CERÁMICA MITSÜ', { negrita: true, tam: 28, despues: 120 }),
    centro('Informe final · Ciclos I, II y III (PUDS)', { tam: 24, despues: 360 }),
);
const dato = (k, v) => new d.Paragraph({ spacing: { after: 100 }, indent: { left: 1440 }, children: [new d.TextRun({ text: k + ': ', bold: true, font: W.FUENTE }), new d.TextRun({ text: v, font: W.FUENTE })] });
add(
    dato('Materia', 'Ingeniería en Requisitos y Modelado (INF342)'),
    dato('Docente', 'M.Sc. Ing. Angélica Garzón Cuéllar'),
    dato('Integrantes', ''),
    ...INTEGRANTES.map(n => new d.Paragraph({ indent: { left: 2160 }, spacing: { after: 60 }, children: [new d.TextRun({ text: '• ' + n, font: W.FUENTE })] })),
    new d.Paragraph({ spacing: { before: 600 }, alignment: d.AlignmentType.CENTER, children: [new d.TextRun({ text: 'Santa Cruz de la Sierra – Bolivia', font: W.FUENTE, bold: true })] }),
    centro('Octubre de 2026', { negrita: true }),
);
add(salto());
add(
    centro('Enlaces del proyecto', { negrita: true, tam: 28, despues: 360 }),
    new d.Table({
        width: { size: 100, type: d.WidthType.PERCENTAGE },
        rows: [new d.TableRow({
            children: [['qr_sistema.png', 'Sistema en línea', SITIO], ['qr_repositorio.png', 'Repositorio en GitHub', REPO]].map(([img, t, url]) => new d.TableCell({
                borders: { top: W.SIN, bottom: W.SIN, left: W.SIN, right: W.SIN },
                children: [
                    new d.Paragraph({ alignment: d.AlignmentType.CENTER, children: [W.imagen(REC(img), 220, 220)] }),
                    new d.Paragraph({ alignment: d.AlignmentType.CENTER, spacing: { before: 120 }, children: [new d.TextRun({ text: t, bold: true, font: W.FUENTE })] }),
                    new d.Paragraph({ alignment: d.AlignmentType.CENTER, children: [new d.ExternalHyperlink({ link: url, children: [new d.TextRun({ text: url, style: 'Hyperlink', size: 20, font: W.FUENTE })] })] }),
                ],
            })),
        })],
    }),
    new d.Paragraph({ spacing: { before: 480 }, children: W.runs('**Cuentas de prueba:** se detallan en el Anexo B. Todas las contraseñas son inventadas para la demostración; las claves reales de la base de datos están en variables de entorno y no aparecen en el repositorio.') }),
);

// ===========================================================================
// ÍNDICE
// ===========================================================================
seccion();
add(
    new d.Paragraph({ alignment: d.AlignmentType.CENTER, spacing: { after: 240 }, children: [new d.TextRun({ text: 'Índice', bold: true, font: W.FUENTE })] }),
    new d.TableOfContents('Índice', { hyperlink: true, headingStyleRange: '1-3' }),
);

// ===========================================================================
// 1. PERFIL
// ===========================================================================
add(H1('1. Perfil'));
add(H2('1.1 Introducción'));
add(
    P('La cerámica artesanal es una forma de expresión cultural que une técnicas tradicionales con diseño contemporáneo. Detrás de cada pieza hay un proceso que involucra materiales, horarios de clases, maestras, inventario y atención a clientes. Cuando todo eso se lleva en cuadernos y hojas de cálculo separadas, el trabajo administrativo crece y la información se pierde.'),
    P('El Taller de Cerámica Mitsü, de Santa Cruz de la Sierra, crea piezas artesanales (vajillas, tazas, platos y piezas artísticas) y enseña modelado a mano y torno a niños, jóvenes y adultos. Hoy organiza sus horarios en Excel y sus finanzas en un software pequeño que no se comunica con lo demás (entrevista a la gerente, Anexo A).'),
    P('Este informe documenta el desarrollo del **Sistema de Información para la Gestión Integral del Taller de Cerámica Mitsü**, una aplicación web construida con el Proceso Unificado de Desarrollo de Software (PUDS) en cuatro ciclos y modelada con UML. Este documento cubre los **ciclos I, II y III**, que ya están implementados y publicados en internet: seguridad y configuración, clases y reservas, inventario, ventas en caja y tienda en línea con pasarela de pago. El ciclo IV (cafetería, cobro de mensualidades y reportes) queda planificado.'),
);
add(H2('1.2 Antecedente'));
add(
    P('El taller nació cuando su fundadora volvió de estudiar Artes Plásticas en Argentina y, junto con una amiga, decidió crear un espacio para producir y enseñar cerámica, porque conseguir arcilla y hornear localmente era difícil (Anexo A). Su estética fusiona el diseño contemporáneo con la esencia de los pueblos originarios de Bolivia, de la región andina y de la Amazonía.'),
    P('Según la entrevista, el taller tiene **4 maestras** y personal administrativo; la cafetería tiene personal propio. La organización es la siguiente: gerencia general; área de producción y enseñanza (maestras ceramistas); área de administración y ventas (atención al cliente, caja y finanzas); y área de cafetería. La materia prima (arcilla, cuarzo y otros minerales) se compra a empresas mineras de Cochabamba y La Paz.'),
);
add(H3('Análisis FODA'));
add(tabla('Análisis FODA del Taller de Cerámica Mitsü', ['Fortalezas', 'Debilidades'], [
    ['Maestras con formación en artes plásticas y experiencia en el oficio.', 'Horarios y reservas organizados a mano en hojas de Excel.'],
    ['Propuesta artística propia: diseño contemporáneo con raíces originarias bolivianas.', 'Sin control sistematizado del inventario de arcilla, esmaltes y minerales.'],
    ['Proveedores fijos de arcilla y minerales (Cochabamba y La Paz).', 'Ventas y caja sin registro centralizado ni arqueo.'],
    ['Servicios diversificados: clases, venta de piezas y cafetería.', 'Herramientas separadas (Excel y software de finanzas) que no se comunican.'],
], null, { anchos: [50, 50] }));
add(tabla('Análisis FODA (continuación)', ['Oportunidades', 'Amenazas'], [
    ['Interés creciente por la cerámica como actividad recreativa y terapéutica.', 'Situación económica que impide contratar más personal.'],
    ['Ampliar talleres y horarios (las clases son lo más solicitado).', 'Competencia de otros talleres de cerámica de la ciudad.'],
    ['Vender piezas en línea y cobrar con QR, tarjeta o PayPal.', 'Dependencia de dos proveedores externos de materia prima.'],
    ['Cafetería como espacio de encuentro para alumnos y visitantes.', 'Variación del precio de materiales y minerales.'],
], 'Basado en la entrevista a la gerente (Anexo A) y en el informe de perfil anterior del grupo.', { anchos: [50, 50] }));
add(H2('1.3 Justificación'));
add(
    P('En la entrevista se identificó que la organización de horarios, el control de materiales y el registro de ventas se hacen a mano o con herramientas que no se integran. Esto provoca pérdida de tiempo al responder reservas una por una, compras urgentes por falta de material y la imposibilidad de saber cuánto se vendió o cuánto efectivo debería haber en caja.'),
    P('Un sistema web integrado permite que el alumno vea los cupos y reserve solo; que la maestra marque la asistencia desde el celular; que el stock de materiales y piezas se actualice automáticamente con cada compra, consumo o venta; que la caja se cierre con arqueo; y que cualquier persona compre piezas en línea. Además, como proyecto académico, permite aplicar el PUDS y UML a un caso real, desde la captura de requisitos hasta un sistema publicado.'),
);
add(H2('1.4 Descripción del problema'));
add(
    P('El taller gestiona sus operaciones con medios manuales y herramientas aisladas. Los horarios están en Excel, por lo que no se ve la disponibilidad en tiempo real y se producen dobles reservas y confusiones en la asignación de maestras. No existe un registro del inventario de materiales, lo que provoca faltantes en momentos críticos y compras urgentes. Las ventas de piezas no tienen un registro centralizado ni comprobante numerado, y no hay cierre de caja que compare el efectivo esperado con el contado. Tampoco existe un canal para que los clientes vean y compren piezas en línea.'),
);
add(H3('1.4.1 Desafíos identificados'));
add(L([
    'Evitar que dos alumnos ocupen el último lugar de una clase al mismo tiempo (control de cupo concurrente).',
    'Impedir que una maestra tenga dos horarios que se crucen.',
    'Que el stock de materiales y piezas no pueda modificarse “a mano”, solo con movimientos registrados (kardex).',
    'Conservar el precio con que se vendió cada pieza aunque después cambie (historial de precios).',
    'Cobrar en línea sin guardar datos sensibles de tarjetas y reservar el stock mientras el cliente paga.',
    'Controlar el acceso por roles y dejar registro (bitácora) de las acciones importantes.',
]));
add(H2('1.5 Formulación del problema'));
add(P('¿Cómo mejorar la gestión de las clases, el inventario y las ventas del Taller de Cerámica Mitsü mediante un sistema de información web que centralice los datos, controle las reglas del negocio y permita a alumnos y clientes reservar y comprar en línea?'));
add(H2('1.6 Objetivos'));
add(H3('1.6.1 Objetivo general'));
add(P('Desarrollar un sistema de información web para la gestión integral del Taller de Cerámica Mitsü que administre la seguridad, las clases y reservas, el inventario, las ventas y la tienda en línea, aplicando el PUDS y UML, y publicarlo en internet.'));
add(H3('1.6.2 Objetivos específicos'));
add(L([
    'Capturar y priorizar los requisitos del taller mediante entrevista, diagrama de Ishikawa y modelo de casos de uso.',
    'Analizar y diseñar la arquitectura del sistema en paquetes, capas y componentes con diagramas UML.',
    'Diseñar una base de datos relacional normalizada en PostgreSQL con reglas de negocio en triggers y procedimientos almacenados.',
    'Implementar el sistema en PHP 8.3 con el patrón MVC y controles de seguridad (roles, permisos, CSRF, contraseñas cifradas, bitácora).',
    'Publicar el sistema en Vercel con la base de datos en Supabase y probarlo con scripts automáticos.',
], true));
add(H2('1.7 Alcance'));
add(P('El sistema se construye en cuatro ciclos del PUDS. Los tres primeros están terminados y publicados; el cuarto queda planificado.'));
add(tabla('Alcance por ciclo', ['Ciclo', 'Paquetes', 'Casos de uso', 'Estado'], [
    ['I', 'P1 Seguridad y configuración', 'CU01 Iniciar y cerrar sesión · CU02 Recuperar contraseña · CU03 Usuarios · CU04 Roles y permisos · CU05 Bitácora · CU06 Configuración y catálogos', 'Terminado'],
    ['II', 'P2 Clases y reservas', 'CU07 Maestras · CU08 Talleres · CU09 Horarios y calendario · CU10 Reservar clase · CU11 Asistencia · CU12 Alumnos y portal', 'Terminado'],
    ['III', 'P3 Inventario y compras · P4 Ventas, caja y tienda', 'CU13 Materiales · CU14 Proveedores · CU15 Compras · CU16 Consumo, merma y alertas · CU17 Piezas · CU18 Venta en caja · CU19 Caja y arqueo · CU20 Tienda en línea · CU21 Pago en línea · CU22 Pedidos web', 'Terminado'],
    ['IV', 'P5 Cafetería · P6 Cobros · P7 Reportes', 'CU23 Menú · CU24 Comandas · CU25 Mensualidades y morosidad · CU26 Pedidos personalizados · CU27 Notificaciones · CU28 Reportes', 'Planificado'],
], 'El alcance incluye el despliegue en internet (Vercel y Supabase). La pasarela de pago funciona en modo de demostración: no realiza cobros reales.', { anchos: [8, 22, 56, 14] }));
add(P('**No incluye** en esta entrega: facturación electrónica ante Impuestos Nacionales, cobros reales con un banco o con PayPal (requieren contrato comercial), ni aplicaciones móviles nativas; el sistema es web y se adapta al celular.'));
add(H2('1.8 Entrevista'));
add(P('Se realizó una entrevista a la gerente del taller, Alessandra Civardi Soria, en las instalaciones del taller (duración: 22 minutos), conducida por Mauricio Miranda Soria. Sus respuestas principales se resumen en la Tabla 4 y la transcripción completa está en el Anexo A.'));
add(tabla('Resumen de la entrevista a la gerente', ['Tema', 'Respuesta resumida'], [
    ['Productos', 'Vajillas, tazas y platos, con enfoque utilitario o artístico según el pedido.'],
    ['Maestras y horarios', '4 maestras. Martes a viernes 10:00–12:00; martes y miércoles 18:00–20:00; sábado adultos 10:00–12:00, niños 16:00–18:00 y modelado y torno 18:00–20:00.'],
    ['Lo más solicitado', 'Las clases, porque las piezas terminadas son más costosas.'],
    ['Personal', 'No se ampliará por ahora por la situación económica.'],
    ['Materiales', 'Arcilla, cuarzo y minerales comprados a empresas mineras de Cochabamba y La Paz.'],
    ['Herramientas actuales', 'Excel para horarios y un pequeño software para finanzas.'],
    ['Otros servicios', 'Cafetería con personal propio e ingredientes naturales (ciclo IV).'],
], 'Basado en la entrevista del Anexo A.', { anchos: [25, 75] }));

// ===========================================================================
// 2. ELEMENTOS DEL SISTEMA BASADO EN COMPUTADORAS
// ===========================================================================
add(H1('2. Elementos del sistema basado en computadoras'));
add(H2('2.1 Hardware'));
add(H3('2.1.1 Servidor'));
add(P('El sistema no necesita un servidor propio en el taller: se ejecuta en la nube. La aplicación PHP corre en Vercel como función sin servidor (región gru1, São Paulo) y la base de datos PostgreSQL está en Supabase (región sa-east-1). Ambos servicios escalan solos y se pagan por uso; en su plan gratuito alcanzan para el volumen del taller.'));
add(H3('2.1.2 Cliente'));
add(P('Cualquier computadora, tableta o celular con un navegador actualizado (Chrome, Edge, Firefox o Safari). Recomendado para recepción: procesador de 2 núcleos, 4 GB de RAM y pantalla de 13 pulgadas o más para el punto de venta.'));
add(H3('2.1.3 Medios de comunicación'));
add(P('Conexión a internet de banda ancha (se recomienda 10 Mbps o más) por Wi-Fi o cable. Todo el tráfico viaja cifrado por HTTPS; la conexión de la aplicación a la base de datos usa TLS.'));
add(H3('2.1.4 Otros dispositivos'));
add(P('Impresora (térmica o común) para la nota de venta y el comprobante; celular de la maestra para registrar asistencia; celular del cliente para pagar con el QR del taller.'));
add(H2('2.2 Software'));
add(H3('2.2.1 Servidor'));
add(tabla('Software del lado del servidor', ['Componente', 'Elección', 'Uso'], [
    ['Lenguaje', 'PHP 8.3 (sin framework, MVC propio)', 'Lógica de la aplicación'],
    ['Plataforma', 'Vercel con vercel-php 0.7.4', 'Publicación y ejecución en la nube'],
    ['Base de datos', 'PostgreSQL 17 en Supabase, esquema propio “mitsu”', 'Datos, triggers y procedimientos'],
    ['Conexión', 'PDO con pdo_pgsql por el pooler Supavisor (puerto 6543)', 'Acceso seguro y con parámetros'],
    ['Correo', 'SMTP de Gmail (se configura con contraseña de aplicación)', 'Comprobantes y recuperación de contraseña'],
], null, { anchos: [20, 42, 38] }));
add(H3('2.2.2 Cliente'));
add(P('Navegador web. La interfaz usa HTML5, CSS3 y JavaScript sin librerías externas pesadas; funciona en modo claro y oscuro y se adapta al celular.'));
add(H3('2.2.3 Otro software adicional'));
add(P('Git y GitHub para el control de versiones; Visual Studio Code como editor; LibreOffice y la librería docx para el informe; Puppeteer para capturar las pantallas y probar el diseño en celular.'));
add(H2('2.3 Datos'));
add(P(`La información se guarda en ${Object.keys(ESQUEMA).length} tablas relacionales: usuarios, roles y permisos; maestras, alumnos, talleres, horarios, clases y reservas; proveedores, materiales, compras y movimientos (kardex); piezas, clientes, cajas, ventas, pedidos en línea y pagos. Las contraseñas se guardan cifradas con bcrypt y de las tarjetas solo se guarda la marca y los cuatro últimos dígitos. El volumen es bajo (miles de registros por año; ver la tabla de volumen en el capítulo 5).`));
add(H2('2.4 Procesos'));
add(L([
    '**Seguridad:** ingreso con correo y contraseña, recuperación por código, usuarios, roles, permisos y bitácora.',
    '**Clases:** talleres, horarios, generación del calendario, reservas con control de cupo y asistencia.',
    '**Inventario:** materiales, proveedores, compras, consumo, merma, ajustes y alertas de stock mínimo.',
    '**Ventas:** piezas con foto, venta en caja con nota de venta, anulación con motivo, apertura y cierre de caja.',
    '**Tienda en línea:** catálogo público, carrito, pedido con reserva de stock, pago con QR, tarjeta o PayPal y gestión de pedidos.',
]));
add(H2('2.5 Gente / usuarios'));
add(tabla('Usuarios del sistema', ['Rol', 'Quién es', 'Qué hace en el sistema'], [
    ['Administrador', 'Gerente del taller', 'Todo: usuarios, roles, bitácora, configuración, talleres, maestras, inventario, ventas y anulaciones.'],
    ['Administrativo', 'Personal de recepción y ventas', 'Reservas, alumnos, asistencia, materiales, compras, piezas, ventas, caja y pedidos web.'],
    ['Maestra', 'Las 4 maestras ceramistas', 'Ve sus clases y registra la asistencia de sus alumnos.'],
    ['Alumno', 'Persona inscrita en talleres', 'Portal: reserva y cancela clases, ve su historial y compra en la tienda.'],
    ['Cliente', 'Comprador en línea', 'Carrito, pago con QR, tarjeta o PayPal, sus pedidos y comprobantes.'],
    ['Visitante', 'Cualquier persona', 'Ve la tienda y las clases sin iniciar sesión.'],
], null, { anchos: [18, 27, 55] }));
add(H2('2.6 Documento'));
add(P('Documentos que produce el sistema: nota de venta numerada (NV-000000), comprobante de compra (CP-000000), comprobante de pedido en línea (PW-000000) con el método de pago, resumen del arqueo de caja y kardex de cada material y pieza. Documentación del proyecto: este informe, el README del repositorio, el guion de presentación y el manual de uso (Anexo D).'));

// ===========================================================================
// 3. TECNOLOGÍA PARA EL DESARROLLO DEL SOFTWARE
// ===========================================================================
add(H1('3. Tecnología para el desarrollo del software'));
add(H2('3.1 Estrategia para el desarrollo del software'));
add(P('Se eligió una estrategia **iterativa e incremental**: el sistema se divide en cuatro ciclos y cada ciclo entrega casos de uso terminados, probados y publicados. Así el taller puede usar la seguridad y las reservas mientras se construye el inventario y las ventas, y los riesgos más altos (control de cupo, stock y pagos) se resuelven temprano (Jacobson et al., 2000).'));
add(H2('3.2 Metodología para el desarrollo del software'));
add(P('Se usa el **Proceso Unificado de Desarrollo de Software (PUDS)** con la notación **UML**. En cada ciclo se recorren los flujos de trabajo de requisitos, análisis, diseño, implementación y prueba.'));
add(H3('3.2.1 Características del PUDS'));
add(L([
    '**Dirigido por casos de uso:** los casos de uso guían el análisis, el diseño y las pruebas.',
    '**Centrado en la arquitectura:** se define temprano la arquitectura en paquetes y capas.',
    '**Iterativo e incremental:** cada ciclo agrega funcionalidad sobre la anterior.',
    '**Fases:** inicio, elaboración, construcción y transición, con hitos en cada una.',
]));
add(H3('3.2.2 Características del UML'));
add(P('UML es un lenguaje gráfico estándar para especificar, visualizar y documentar sistemas (Booch et al., 2006). En este proyecto se usan diagramas de casos de uso, actividad, comunicación, clases, paquetes, secuencia, despliegue y componentes, con los estereotipos de análisis «frm» (interfaz), «ctrl» (control) y «entidad».'));
add(H2('3.3 Herramientas de desarrollo'));
add(H3('3.3.1 Software'));
add(tabla('Herramientas de software', ['Herramienta', 'Uso', 'Costo'], [
    ['Visual Studio Code', 'Editor de código', 'Gratuito'], ['PHP 8.3 y PostgreSQL 17', 'Lenguaje y base de datos', 'Gratuito (código abierto)'],
    ['Git y GitHub', 'Control de versiones y repositorio público', 'Gratuito'], ['Vercel y Supabase (planes gratuitos)', 'Publicación y base en la nube', 'Gratuito'],
    ['Python, Node.js y Puppeteer', 'Diagramas, informe y capturas de pantalla', 'Gratuito'], ['LibreOffice', 'Índice y exportación a PDF', 'Gratuito'],
], null, { anchos: [35, 45, 20] }));
add(H3('3.3.2 Hardware'));
add(P('Cinco computadoras portátiles de los integrantes (procesador de 4 núcleos, 8 GB de RAM o más) y conexión a internet. No se compró hardware.'));

// ===========================================================================
// 4–6. COSTOS, BENEFICIOS Y CLIENTES
// ===========================================================================
add(H1('4. Posibles costos'));
add(P('Los montos de esta sección son **estimados** por el equipo; no provienen de cotizaciones formales. Se usa el tipo de cambio oficial de Bs 6,96 por dólar.'));
add(H2('4.1 Costos de desarrollo'));
add(tabla('Costo estimado de desarrollo', ['Concepto', 'Cálculo', 'Monto (Bs)'], [
    ['Análisis y diseño (requisitos, UML, base de datos)', '5 personas × 50 h × Bs 25/h', '6.250'],
    ['Programación y pruebas (ciclos I a III)', '5 personas × 90 h × Bs 25/h', '11.250'],
    ['Documentación e informe', '5 personas × 20 h × Bs 25/h', '2.500'],
    ['Total estimado', '', '20.000'],
], 'Estimado. Bs 25 por hora corresponde a una referencia de pago a practicantes; las horas son aproximadas.', { anchos: [50, 32, 18], derecha: [2] }));
add(H2('4.2 Costos de software'));
add(P('Todo el software usado es libre o gratuito (Tabla 7), por lo que el costo de licencias es **Bs 0**.'));
add(H2('4.3 Costos de infraestructura'));
add(tabla('Costo estimado de infraestructura', ['Concepto', 'Plan', 'Costo mensual (Bs)'], [
    ['Publicación de la aplicación', 'Vercel Hobby (actual)', '0'], ['Base de datos', 'Supabase Free (actual)', '0'],
    ['Base de datos recomendada en producción (sin pausa por inactividad, respaldos diarios)', 'Supabase Pro, USD 25/mes', '174'],
    ['Dominio propio .com (opcional)', 'USD 12/año', '7'], ['Correo SMTP', 'Gmail con contraseña de aplicación', '0'],
], 'Estimado según los precios públicos de los proveedores consultados por el equipo; deben verificarse al contratar.', { anchos: [52, 30, 18], derecha: [2] }));
add(H1('5. Posibles beneficios'));
add(P('Los valores de esta sección son **estimaciones** del equipo a partir de la entrevista y de las pruebas del sistema; se deben medir con el uso real.'));
add(H2('5.1 Tiempo'));
add(L([
    'Reservar una clase pasa de una conversación por WhatsApp (estimado: 5 a 10 minutos) a menos de 1 minuto desde el portal del alumno.',
    'El cierre de caja con arqueo se calcula solo; antes había que sumar a mano las ventas del día.',
    'El stock de cada material se consulta en segundos en lugar de revisar los estantes.',
]));
add(H2('5.2 Esfuerzo'));
add(L([
    'La recepción ya no responde cupos uno por uno: el calendario muestra los lugares libres.',
    'La maestra registra la asistencia con un toque por alumno desde el celular.',
    'Las compras actualizan stock y costo promedio de forma automática.',
]));
add(H2('5.3 Costos'));
add(L([
    'Menos compras urgentes de material gracias a las alertas de stock mínimo (estimado: se evitan 1 o 2 envíos urgentes al mes desde Cochabamba o La Paz).',
    'Menos diferencias de caja no explicadas, porque cada venta queda registrada y la anulación exige motivo.',
    'Ventas adicionales por la tienda en línea, que funciona a toda hora.',
]));
add(H1('6. Posibles clientes'));
add(P('El cliente principal es el Taller de Cerámica Mitsü. Por estar organizado en paquetes y roles configurables, el sistema puede adaptarse a otros negocios que **enseñan y venden**: talleres de cerámica, pintura o carpintería; academias de arte, música o idiomas con venta de materiales; estudios de yoga o danza con tienda; y emprendimientos artesanales que necesitan inventario de insumos y venta en línea.'));

// ===========================================================================
// 7–9. MODELO DE DOMINIO, MODELO DE NEGOCIO, MARCO TEÓRICO
// ===========================================================================
add(H1('7. Modelo de dominio'));
add(P('El modelo de dominio muestra los conceptos del negocio y cómo se relacionan, sin detalles técnicos. Un usuario tiene un rol con permisos y puede ser maestra o alumno. Un taller tiene horarios dictados por una maestra; cada horario genera clases y los alumnos reservan lugares en ellas. Las compras a proveedores incluyen materiales. Las ventas en caja y los pedidos en línea incluyen piezas; el pedido se paga con uno o más intentos de pago.'));
add(figura('Modelo de dominio del Taller Mitsü', DIAG('dominio'), NOTA_FUENTE));
add(H1('8. Modelo de negocio'));
add(P('El diagrama de actividad describe cómo trabaja hoy el taller, antes del sistema: el alumno consulta horarios por WhatsApp, recepción revisa el cuaderno y anota la reserva, la maestra dicta la clase y anota la asistencia en papel, las ventas se anotan a mano y la gerencia cuenta el dinero y revisa el stock al cierre para decidir compras.'));
add(figura('Diagrama de actividad del proceso actual (modelo de negocio)', DIAG('actividad_negocio'), 'Elaboración propia a partir de la entrevista (Anexo A).', { alto: 600 }));
add(H1('9. Marco teórico'));
const teoria = [
    ['Sistema de información', 'Conjunto de personas, datos, procesos y tecnología que recoge, guarda, procesa y distribuye información para apoyar las operaciones y la toma de decisiones de una organización (Laudon y Laudon, 2020).'],
    ['Proceso Unificado (PUDS)', 'Proceso de desarrollo dirigido por casos de uso, centrado en la arquitectura, iterativo e incremental, organizado en fases e iteraciones (Jacobson et al., 2000).'],
    ['UML', 'Lenguaje de modelado estándar del Object Management Group para especificar y documentar sistemas con diagramas de estructura y de comportamiento (Booch et al., 2006).'],
    ['Patrón Modelo-Vista-Controlador (MVC)', 'Separa los datos y reglas (modelo), la presentación (vista) y la coordinación de las peticiones (controlador), lo que facilita mantener y probar el sistema (Larman, 2004).'],
    ['Base de datos relacional y normalización', 'Los datos se organizan en tablas relacionadas por llaves; la normalización (1FN, 2FN, 3FN) elimina redundancias y anomalías de actualización (Elmasri y Navathe, 2016).'],
    ['Triggers y procedimientos almacenados', 'Código que se ejecuta dentro del gestor de base de datos; los triggers reaccionan a inserciones o cambios y garantizan reglas del negocio aunque falle la aplicación (PostgreSQL Global Development Group, 2024).'],
    ['Seguridad en aplicaciones web', 'Las amenazas más comunes (inyección SQL, XSS, CSRF, control de acceso roto) se mitigan con consultas parametrizadas, escape de salida, tokens CSRF, contraseñas con hash y permisos verificados en el servidor (OWASP Foundation, 2021).'],
    ['Pasarela de pago', 'Servicio que autoriza cobros con tarjeta u otros medios. El número de tarjeta se valida con el algoritmo de Luhn (Luhn, 1960) y las normas PCI DSS prohíben guardar el número completo o el CVV (PCI Security Standards Council, 2022).'],
    ['Computación en la nube', 'Modelo en que la infraestructura se usa como servicio: plataforma como servicio para la aplicación (Vercel) y base de datos como servicio (Supabase), sin administrar servidores propios (Mell y Grance, 2011).'],
    ['Diagrama de Ishikawa', 'Diagrama causa-efecto que organiza las causas de un problema en categorías para analizarlas (Ishikawa, 1985).'],
];
teoria.forEach(([t, x]) => add(H3(t), P(x)));

// ===========================================================================
// CAPÍTULO 1. MÉTODO DE ISHIKAWA
// ===========================================================================
add(H1('Capítulo 1. Método de Ishikawa'));
add(H2('1. Identificar problemas'));
add(H3('1.1 Lista de problemas'));
const PROBLEMAS = [
    ['Gestión de horarios de clases realizada manualmente con hojas de Excel.', 'Comercial'],
    ['No existe un medio para que los alumnos consulten la disponibilidad en tiempo real.', 'Comercial'],
    ['Confusión en la asignación de maestras por falta de un registro centralizado.', 'Comercial'],
    ['No existe un registro sistematizado del inventario de materiales.', 'Inventario'],
    ['Falta de materiales en momentos críticos.', 'Inventario'],
    ['Compras urgentes o innecesarias sin planificación.', 'Compras'],
    ['Pérdida de tiempo en la verificación manual de existencias.', 'Inventario'],
    ['Las ventas de piezas no tienen registro centralizado.', 'Ventas'],
    ['Las ventas de la cafetería no se integran con el resto del taller.', 'Ventas'],
    ['Dificultad para generar reportes de ventas.', 'Ventas'],
    ['Falta de indicadores para decidir.', 'Gerencia'],
    ['Descoordinación entre producción, administración y cafetería.', 'Comercial'],
    ['Retrasos en la atención a clientes.', 'Comercial'],
    ['Dependencia de herramientas externas no integradas.', 'Todos'],
    ['Dificultad para organizar la demanda de clases.', 'Comercial'],
    ['No existe historial de asistencia de los alumnos.', 'Comercial'],
    ['No hay control de pagos y morosidad de los alumnos.', 'Ventas'],
    ['La información de clientes y alumnos está dispersa.', 'Comercial'],
    ['No hay registro de pedidos personalizados.', 'Ventas'],
    ['Duplicidad de información y errores de registro.', 'Todos'],
    ['Riesgo de pérdida de datos sin respaldos.', 'Todos'],
];
add(tabla('Lista inicial de problemas', ['N.º', 'Problema', 'Proceso'], PROBLEMAS.map((p, i) => [String(i + 1), ...p]),
    'Tomado del informe de perfil anterior del grupo, construido a partir de la entrevista (Anexo A).', { anchos: [8, 72, 20] }));
add(H3('1.2 Depurar problemas'));
add(P('Se agruparon los problemas que eran consecuencia de otro (por ejemplo, la falta de materiales es consecuencia de no tener inventario) y se separaron los que corresponden al ciclo IV (cafetería, mensualidades, pedidos personalizados y reportes).'));
add(tabla('Depuración de problemas', ['Problemas', 'Acción', 'Justificación'], [
    ['1, 2, 3, 15', 'Unir', 'Todos nacen de llevar horarios y reservas en Excel.'],
    ['4, 5, 6, 7', 'Unir', 'Son consecuencias de no tener inventario ni kardex.'],
    ['8, 10, 11', 'Unir', 'Sin registro de ventas no hay reportes ni indicadores.'],
    ['12, 13, 14, 18, 20', 'Unir', 'Información dispersa en herramientas que no se comunican.'],
    ['16', 'Mantener', 'Problema propio del proceso de clases.'],
    ['21', 'Mantener', 'Riesgo técnico que el sistema resuelve con la nube y respaldos.'],
    ['9, 17, 19', 'Postergar al ciclo IV', 'Cafetería, cobro de mensualidades y pedidos personalizados.'],
], null, { anchos: [22, 20, 58] }));
add(H3('1.3 Lista final de problemas'));
const FINAL = [
    'P1. Horarios y reservas manuales: sin cupos en tiempo real y con dobles reservas.',
    'P2. Sin historial de asistencia de los alumnos.',
    'P3. Inventario de materiales sin registro: faltantes y compras urgentes.',
    'P4. Ventas de piezas sin registro, sin nota numerada y sin cierre de caja.',
    'P5. Información dispersa en herramientas no integradas y sin control de acceso.',
    'P6. Clientes que no pueden ver ni comprar piezas en línea.',
    'P7. Riesgo de pérdida de datos sin respaldos.',
];
add(L(FINAL));
add(H3('1.4 Lista de propietarios de problemas'));
add(tabla('Matriz problema – propietario', ['Problema', 'Gerencia', 'Recepción', 'Maestras', 'Alumnos / clientes'], [
    ['P1 Horarios y reservas', 'X', 'X', 'X', 'X'], ['P2 Asistencia', 'X', '', 'X', ''], ['P3 Inventario', 'X', 'X', 'X', ''],
    ['P4 Ventas y caja', 'X', 'X', '', 'X'], ['P5 Información dispersa', 'X', 'X', '', ''], ['P6 Venta en línea', 'X', '', '', 'X'],
    ['P7 Respaldos', 'X', '', '', ''],
], 'X indica que el problema afecta a ese propietario.', { anchos: [36, 16, 16, 16, 16] }));
add(H3('1.5 Análisis de problemas'));
add(tabla('Análisis causa – efecto de cada problema', ['Problema', 'Causa principal', 'Efecto'], [
    ['P1', 'Excel sin acceso para alumnos ni control de cupo.', 'Dobles reservas, tiempo de recepción, maestras mal asignadas.'],
    ['P2', 'Asistencia anotada en papel.', 'No se sabe qué alumnos faltan seguido.'],
    ['P3', 'No hay kardex ni stock mínimo.', 'Faltantes en plena producción y compras urgentes más caras.'],
    ['P4', 'Ventas anotadas a mano.', 'No se puede comparar el efectivo con lo vendido.'],
    ['P5', 'Herramientas separadas.', 'Datos repetidos y errores.'],
    ['P6', 'No existe tienda en línea.', 'Se pierden ventas fuera del horario del local.'],
    ['P7', 'Archivos en computadoras personales.', 'Riesgo de perder la información.'],
], null, { anchos: [12, 40, 48] }));
add(H3('1.6 Estimación y cuantificación'));
add(tabla('Estimación del tiempo perdido por semana', ['Problema', 'Actividad', 'Tiempo semanal estimado'], [
    ['P1', 'Responder consultas y anotar reservas', '4 a 6 horas'], ['P2', 'Pasar la asistencia del papel', '1 hora'],
    ['P3', 'Revisar existencias y gestionar compras urgentes', '2 horas'], ['P4', 'Sumar ventas y cuadrar la caja', '2 a 3 horas'],
    ['P5', 'Buscar datos en distintos archivos', '1 a 2 horas'], ['Total', '', '10 a 14 horas'],
], 'Estimado por el equipo a partir de la entrevista; no fue medido en el taller.', { anchos: [15, 55, 30] }));
add(H3('1.7 Alternativas de cambio'));
add(tabla('Comparación de alternativas', ['Criterio', 'A. Mejorar las hojas de Excel', 'B. Software comercial genérico', 'C. Sistema web a medida'], [
    ['Reservas con cupo en línea', 'No', 'Parcial', 'Sí'], ['Inventario con kardex', 'Manual', 'Sí', 'Sí'],
    ['Venta en caja y tienda en línea', 'No', 'Por separado', 'Integradas'], ['Reglas del taller (talleres, maestras)', 'No', 'Difícil de adaptar', 'Sí'],
    ['Costo inicial', 'Bajo', 'Licencias mensuales', 'Desarrollo académico'], ['Acceso desde el celular', 'Limitado', 'Depende', 'Sí'],
], null, { anchos: [31, 23, 23, 23] }));
add(H3('1.8 Conclusión y recomendación'));
add(P('La alternativa C, un sistema web a medida, es la única que resuelve los siete problemas en una sola herramienta y se adapta a la forma de trabajo del taller. Se recomienda desarrollarla en ciclos, empezando por la seguridad y las reservas (P1, P2, P5), luego el inventario y las ventas (P3, P4, P6), y publicarla en la nube para eliminar el riesgo de pérdida de datos (P7).'));
add(H3('1.9 Diseñar el diagrama de Ishikawa'));
add(P('El efecto (cabeza del pescado) es la gestión manual y desintegrada del taller. Las espinas son las seis categorías de causas, descritas a continuación; el diagrama completo se presenta en la página horizontal siguiente.'));
add(H2('2. Identificar las principales categorías'));
add(L(['**Métodos y procesos:** cómo se hacen hoy las reservas, las ventas y el cierre.', '**Personas:** personal reducido y tareas repartidas.', '**Tecnología:** herramientas aisladas y sin control de acceso.', '**Materiales e inventario:** insumos sin registro.', '**Medición y control:** falta de historial e indicadores.', '**Clientes y entorno:** lo que el cliente no puede hacer y la competencia.']));
add(H2('3. Identificar las causas'));
add(P('Para cada categoría se identificaron tres causas a partir de la lista final de problemas; se muestran en las espinas secundarias del diagrama.'));
seccion(true);
add(figura('Diagrama de Ishikawa del Taller Mitsü', DIAG('ishikawa'), 'Elaboración propia a partir de la entrevista (Anexo A) y de la lista final de problemas.', { alto: 470 }));
seccion();
add(H2('4. Analizar y discutir el diagrama'));
add(P('Las categorías con más peso son **métodos y procesos** y **tecnología**: casi todos los problemas nacen de anotar en Excel o en papel lo que debería registrarse una sola vez. Por eso el sistema se centra en que cada dato se ingrese una vez y el resto se calcule solo: el cupo libre sale de las reservas, el stock sale del kardex, el total de caja sale de las ventas. Las causas de **personas** (no se puede contratar más personal) refuerzan la necesidad de automatizar; las de **clientes y entorno** justifican la tienda en línea y el portal del alumno.'));
add(H2('5. Modelo de negocio'));
add(H3('5.1 Diagrama de actividad'));
add(P('El diagrama de actividad del proceso actual se presentó en la sección 8 (Figura 2). Sus actividades manuales —consultar por WhatsApp, revisar el cuaderno, anotar en papel, contar el dinero— son las que el sistema reemplaza en los ciclos I a III.'));

// ===========================================================================
// CAPÍTULO 2. CAPTURA DE REQUISITOS
// ===========================================================================
add(H1('Capítulo 2. Flujo de trabajo: captura de requisitos'));
add(H2('2.1 Identificar actores y casos de uso'));
add(H3('Actores'));
add(tabla('Actores del sistema', ['Actor', 'Descripción'], [
    ['Visitante', 'Persona sin cuenta que ve la tienda y las clases.'], ['Cliente', 'Visitante que creó su cuenta para comprar en línea.'],
    ['Alumno', 'Persona inscrita que reserva clases desde su portal; también puede comprar.'], ['Maestra', 'Dicta talleres y registra la asistencia de sus clases.'],
    ['Administrativo', 'Recepción y ventas: reservas, inventario, compras, ventas, caja y pedidos web.'], ['Administrador', 'Gerencia: además de lo anterior, usuarios, roles, bitácora, configuración, maestras y talleres.'],
], null, { anchos: [22, 78] }));
add(figura('Generalización de actores', DIAG('actores'), NOTA_FUENTE, { ancho: 520 }));
add(H3('Casos de uso'));
CICLOS.forEach(([c, t]) => {
    add(H4(t));
    add(tabla(`Casos de uso del ${t.split(':')[0].toLowerCase()}`, ['Código', 'Caso de uso', 'Actores', 'Paquete'],
        deCiclo(c).map(x => [x.id, x.nombre, x.actores.map(a => ACTORES[a]).join(', '), x.paquete]), null, { anchos: [12, 46, 28, 14] }));
});
add(H4('Ciclo IV: cafetería, cobros y reportes (planificado)'));
add(P('CU23 Gestionar menú de cafetería · CU24 Registrar comandas · CU25 Cobrar mensualidades y controlar morosidad · CU26 Pedidos personalizados · CU27 Notificaciones · CU28 Reportes e indicadores. No forman parte de esta entrega.'));
add(H2('2.2 Priorizar casos de uso'));
add(P('La prioridad se asignó según el valor para el taller y el riesgo técnico: los casos de prioridad alta y riesgo alto se construyeron primero dentro de cada ciclo, porque concentran las reglas más delicadas (cupo, stock, dinero).'));
CICLOS.forEach(([c, t]) => {
    add(H4(t));
    add(tabla(`Priorización del ${t.split(':')[0].toLowerCase()}`, ['Código', 'Caso de uso', 'Prioridad', 'Riesgo'],
        deCiclo(c).map(x => [x.id, x.nombre, x.prioridad, x.riesgo]), null, { anchos: [12, 58, 15, 15] }));
});
add(H2('2.3 Detallar un caso de uso'));
CICLOS.forEach(([c, t]) => {
    add(H3(t));
    deCiclo(c).forEach(x => add(ficha(x, ACTORES)));
});
add(H2('2.4 Prototipar interfaz de usuario'));
add(P('Los prototipos son las pantallas reales del sistema publicado, capturadas en computadora (1366 × 800) y en celular. La interfaz usa la paleta de colores arcilla y crema del taller.'));
const PROTOS = {
    I: [['CU01_login', 'CU01 Pantalla de ingreso'], ['CU01_panel', 'CU01 Panel del administrador después de ingresar'], ['CU02_recuperar', 'CU02 Recuperar contraseña'],
        ['CU03_usuarios', 'CU03 Lista de usuarios'], ['CU03_usuario_nuevo', 'CU03 Formulario de nuevo usuario'], ['CU04_roles', 'CU04 Roles y matriz de permisos'],
        ['CU05_bitacora', 'CU05 Bitácora con filtros'], ['CU06_configuracion', 'CU06 Configuración y catálogos']],
    II: [['CU07_maestras', 'CU07 Lista de maestras'], ['CU07_maestra_nueva', 'CU07 Registro de maestra'], ['CU08_talleres', 'CU08 Talleres'],
         ['CU09_horarios', 'CU09 Horarios semanales'], ['CU09_calendario', 'CU09 Calendario de clases'], ['CU10_reservar', 'CU10 Portal del alumno: reservar clase'],
         ['CU10_clase', 'CU10 Detalle de una clase con sus reservas (recepción)'], ['CU11_panel_maestra', 'CU11 Panel de la maestra'], ['CU11_asistencia', 'CU11 Registro de asistencia'],
         ['CU12_registro', 'CU12 Autorregistro de alumno'], ['CU12_alumnos', 'CU12 Lista de alumnos'], ['CU12_portal', 'CU12 Portal del alumno']],
    III: [['CU13_materiales', 'CU13 Materiales con alertas de stock'], ['CU14_proveedores', 'CU14 Proveedores'], ['CU15_compra_nueva', 'CU15 Nueva compra'],
          ['CU15_compra', 'CU15 Comprobante de compra'], ['CU16_kardex', 'CU16 Kardex de un material'], ['CU17_piezas', 'CU17 Piezas de cerámica'],
          ['CU17_pieza_form', 'CU17 Formulario de pieza con foto'], ['CU18_punto_venta', 'CU18 Punto de venta'], ['CU18_nota', 'CU18 Nota de venta'],
          ['CU19_caja', 'CU19 Apertura y cierre de caja'], ['CU20_tienda', 'CU20 Página principal: la tienda se ve primero'], ['CU20_tienda_movil', 'CU20 Tienda en el celular'],
          ['CU20_pieza', 'CU20 Detalle de una pieza'], ['CU20_carrito', 'CU20 Carrito'], ['CU20_checkout', 'CU20 Confirmar pedido'],
          ['CU21_pasarela', 'CU21 Pasarela: pago con el QR del taller'], ['CU21_tarjeta', 'CU21 Pasarela: pago con tarjeta'], ['CU21_comprobante', 'CU21 Comprobante del pedido'],
          ['CU20_mis_pedidos', 'CU20 Mis pedidos'], ['CU22_pedidos', 'CU22 Pedidos web (taller)'], ['CU22_pedido', 'CU22 Detalle del pedido y verificación del QR']],
};
CICLOS.forEach(([c, t]) => {
    add(H3(t));
    PROTOS[c].forEach(([f, tt]) => fs.existsSync(CAP(f)) && add(figura(tt, CAP(f), `Captura del sistema publicado en ${SITIO}.`, f.endsWith('movil') ? { ancho: 250, alto: 540 } : { alto: 315 })));
});
add(H2('2.5 Estructurar el modelo de casos de uso'));
add(figura('Diagrama general: actores y paquetes', DIAG('cu_general'), NOTA_FUENTE));
CICLOS.forEach(([c, t]) => {
    add(H3(t));
    add(figura(`Diagrama de casos de uso del ${t.split(':')[0].toLowerCase()}`, DIAG(`cu_ciclo_${c}`), NOTA_FUENTE, { alto: 600 }));
});

// ===========================================================================
// CAPÍTULO 3. ANÁLISIS
// ===========================================================================
add(H1('Capítulo 3. Flujo de trabajo: análisis'));
add(H2('3.1 Análisis de la arquitectura'));
add(H3('3.1.1 Identificar paquetes'));
add(tabla('Paquetes de análisis', ['Paquete', 'Responsabilidad', 'Ciclo'], [
    ['P1 Seguridad y configuración', 'Acceso, usuarios, roles, permisos, bitácora, catálogos', 'I'],
    ['P2 Clases y reservas', 'Maestras, talleres, horarios, calendario, reservas, asistencia, alumnos', 'II'],
    ['P3 Inventario y compras', 'Materiales, proveedores, compras, kardex y alertas', 'III'],
    ['P4 Ventas, caja y tienda en línea', 'Piezas, clientes, ventas, caja, tienda, pedidos y pagos', 'III'],
    ['P5 Cafetería · P6 Cobros · P7 Reportes', 'Menú, comandas, mensualidades, reportes', 'IV (planificado)'],
], null, { anchos: [33, 52, 15] }));
add(H3('3.1.2 Relacionar paquetes y casos de uso'));
add(figura('Paquetes con sus casos de uso', DIAG('paquetes_casos'), NOTA_FUENTE));
add(H3('3.1.3 Vista de paquetes'));
add(P('Todos los paquetes usan P1, porque cada acción verifica la sesión y el permiso. P4 usa P3 porque las piezas comparten categorías con el inventario.'));
add(figura('Vista de paquetes y sus dependencias', DIAG('paquetes'), NOTA_FUENTE, { ancho: 560 }));
add(H2('3.2 Analizar un caso de uso'));
add(P('Para cada caso de uso se elaboró un diagrama de comunicación con las clases de análisis: el actor, la interfaz «frm», el controlador «ctrl» y las entidades «entidad». Los mensajes están numerados en el orden en que ocurren; los que dependen de una condición llevan la guarda entre corchetes y los que se repiten llevan un asterisco. Por su ancho, los diagramas se presentan en páginas horizontales.'));
seccion(true);
CICLOS.forEach(([c, t]) => {
    add(H3(t));
    deCiclo(c).forEach(x => add(figura(`Diagrama de comunicación ${x.id} ${x.nombre}`, DIAG(`com_${x.id}`), NOTA_FUENTE, { alto: 510 })));
});
seccion();
add(H2('3.3 Análisis de clase'));
add(P('Las clases de análisis de cada ciclo se agrupan en tres columnas: interfaces (lo que ve el usuario), controladores (coordinan el caso de uso) y entidades (datos que persisten). Las entidades con nombre fn_ o sp_ son funciones o procedimientos de la base de datos que ejecutan una operación completa en una transacción.'));
CICLOS.forEach(([c, t]) => {
    add(H3(t));
    add(figura(`Clases de análisis del ${t.split(':')[0].toLowerCase()}`, DIAG(`analisis_ciclo_${c}`), NOTA_FUENTE, { alto: 640 }));
});
add(H2('3.4 Análisis de paquetes'));
add(tabla('Contenido de cada paquete', ['Paquete', 'Interfaces', 'Controladores', 'Entidades'], Object.keys(PAQUETES).map(p => {
    const set = t => [...new Set(CASOS.filter(x => x.paquete === p).flatMap(x => x.participantes.filter(q => t.includes(q.tipo)).map(q => q.nombre)))].join(', ');
    return [PAQUETES[p], set(['frm']), set(['ctrl']), set(['ent', 'bd'])];
}), null, { anchos: [19, 27, 22, 32], tam: 18 }));

// ===========================================================================
// CAPÍTULO 4. DISEÑO
// ===========================================================================
add(H1('Capítulo 4. Flujo de trabajo: diseño'));
add(H2('4.1 Diseño de arquitectura'));
add(H3('4.1.1 Diseño físico: diagrama de despliegue'));
add(P('El navegador del usuario se comunica por HTTPS con Vercel, donde corre la aplicación PHP. La aplicación se conecta a PostgreSQL en Supabase a través del pooler Supavisor en modo transacción (puerto 6543) con un usuario de mínimo privilegio. El correo SMTP y el ingreso con Google son servicios externos opcionales.'));
add(figura('Diagrama de despliegue', DIAG('despliegue'), NOTA_FUENTE));
add(H3('4.1.2 Diseño lógico: diagrama organizado en capas'));
add(P('La aplicación sigue el patrón MVC con cinco capas. Cada capa solo usa la de abajo: las vistas no consultan la base de datos y los modelos no generan HTML. Las reglas críticas se repiten en la capa de datos (triggers) como última defensa.'));
add(figura('Arquitectura en capas', DIAG('capas'), NOTA_FUENTE, { ancho: 560 }));

// ===========================================================================
// CAPÍTULO 5. DISEÑO DE DATOS
// ===========================================================================
add(H1('Capítulo 5. Diseño de datos'));
add(H2('5.1 Diseño de datos lógico'));
add(H3('Diagrama de clases persistentes'));
add(P('Las clases persistentes se derivan de las entidades de análisis. Se muestran por paquete; las llaves foráneas se representan como asociaciones y las composiciones (rombo negro) indican que la parte no existe sin el todo, como el detalle de una venta.'));
Object.entries(PAQUETES).forEach(([p, n]) => add(figura(`Clases persistentes de ${n}`, DIAG(`per_${p}`), NOTA_FUENTE, { alto: 600 })));
add(H3('Mapeo de clases a tablas'));
const nombreClase = t => t.split('_').map(s => s[0].toUpperCase() + s.slice(1)).join('');
const restr = (t, tipo) => (ESQUEMA[t].restricciones || []).filter(k => k.tipo === tipo);
add(tabla('Mapeo de clases persistentes a tablas', ['Clase', 'Tabla', 'Llave primaria', 'Llaves foráneas → tabla'],
    Object.keys(ESQUEMA).map(t => [nombreClase(t), t, (restr(t, 'p')[0] || {}).columnas || '', restr(t, 'f').map(k => `${k.columnas} → ${k.referencia}`).join('\n')]),
    'Generada desde el catálogo de PostgreSQL de la base publicada.', { anchos: [20, 20, 18, 42], tam: 18, mono: [1, 2, 3] }));
add(H3('Normalización'));
add(P('**Primera forma normal (1FN):** cada columna guarda un solo valor. En la hoja de Excel del taller una fila decía “Martes y miércoles 10:00–12:00, Rosario y Elena”. En el sistema cada horario es una fila de la tabla horario con un solo día, una sola hora de inicio y fin y una sola maestra.'));
add(tabla('Ejemplo de 1FN: de la hoja de Excel a la tabla horario', ['Antes (Excel)', 'Después (tabla horario)'], [
    ['Modelado | Mar y Mié 10–12 | Rosario, Elena', 'taller=Modelado, dia_semana=2, 10:00–12:00, maestra=Rosario'],
    ['', 'taller=Modelado, dia_semana=3, 10:00–12:00, maestra=Elena'],
], null, { anchos: [45, 55], mono: [1] }));
add(P('**Segunda forma normal (2FN):** todo atributo depende de la llave completa. En rolpermiso, cuya llave es (rol_id, permiso_id), no se guarda el nombre del permiso, porque dependería solo de permiso_id; ese dato está en la tabla permiso. En detalle_venta la llave es id y precio_unitario depende del detalle (es el precio con que se vendió, copiado por un trigger), no de la pieza: es un dato histórico, no una redundancia.'));
add(P('**Tercera forma normal (3FN):** no hay dependencias transitivas. El material no guarda el nombre de su categoría ni de su unidad, sino categoria_id y unidad_id; así, si se renombra “kg” a “kilogramo”, se cambia en un solo lugar. Lo mismo ocurre con el método de pago de una venta y con el rol de un usuario.'));
add(H2('5.2 Diseño de datos físico'));
add(H3('Tabla de volumen'));
const CRECE = { reserva: 1500, sesion_clase: 650, bitacora: 6000, venta: 900, detalle_venta: 1400, movimiento_pieza: 1800, movimiento_material: 400, pedido: 300, detalle_pedido: 450, pago: 380, compra: 60, detalle_compra: 150, caja: 300, sesion: 3000, alumno: 120, cliente: 250, usuario: 150, historial_precio: 30, recuperacion: 40, pieza_imagen: 40, pieza: 40, material: 10 };
add(tabla('Volumen de datos por tabla', ['Tabla', 'Filas actuales', 'Tamaño actual (KB)', 'Crecimiento anual estimado (filas)'],
    BD.volumen.map(v => [v.tabla, String(v.filas), String(Math.round(v.bytes / 1024)), CRECE[v.tabla] ? String(CRECE[v.tabla]) : 'menos de 10']),
    'Filas y tamaño medidos en la base publicada con datos de demostración. El crecimiento anual es una estimación del equipo con base en 7 talleres, 13 horarios semanales y unas 3 ventas diarias.', { anchos: [32, 18, 20, 30], derecha: [1, 2, 3], mono: [0] }));
add(H3('Script'));
add(P(`El script está dividido en siete archivos en la carpeta Bakend/database del repositorio: 01_esquema.sql (tablas, llaves, índices y restricciones), 02_reglas_negocio.sql (triggers RN01 a RN10), 03_datos_iniciales.sql (datos de demostración), 04_usuario_app.sql (usuario de mínimo privilegio y seguridad por filas), 05_consultas.sql (las 30 consultas), 06_procedimientos_triggers.sql (procedimientos PA1 a PA7) y 07_tienda_pasarela.sql (tienda en línea y pagos, PA8 a PA12). En total la base tiene ${Object.keys(ESQUEMA).length} tablas y ${leer('esquema.json').triggers} triggers. A continuación se muestra un fragmento; el script completo está en el Anexo E.`));
const s01 = SQL('01_esquema.sql');
const ini = s01.indexOf('create table horario');
add(codigo(s01.slice(ini, s01.indexOf(');', s01.indexOf('create table reserva')) + 2)));
add(H3('Diagrama relacional'));
Object.entries(PAQUETES).forEach(([p, n]) => add(figura(`Diagrama relacional de ${n}`, DIAG(`rel_${p}`), 'PK: llave primaria; FK: llave foránea. N y 1 indican la cardinalidad. Generado desde las tablas reales de la base publicada.', { alto: 620 })));
add(H3('Actualización de tuplas (población de datos)'));
add(P('La base se pobló con el script 03_datos_iniciales.sql: 5 roles, 23 permisos, 4 maestras, 10 alumnos, 7 talleres con 13 horarios (los de la entrevista), clases de tres semanas antes y después de la fecha actual, proveedores, materiales, compras, piezas con fotos del taller y ventas de 20 días. Los ejemplos siguientes se ejecutaron en la base publicada dentro de una transacción que luego se revirtió, para no alterar los datos.'));
const consulta = id => BD.consultas.find(q => q.id === id);
[['I1', 'Insert()'], ['U1', 'Update()'], ['D1', 'Delete()']].forEach(([id, t]) => {
    const q = consulta(id);
    add(H4(`${t}: ${q.titulo}`), codigo(q.sql), P(`Resultado: ${q.total} fila(s) afectada(s).`, { sinSangria: true }), resultado(q.columnas, q.filas));
});
add(H3('Consultas (30 SQL)'));
add(P('Las 30 consultas se ejecutaron contra la base publicada con el usuario de la aplicación; se muestran hasta seis filas del resultado real de cada una.'));
[['Consultas simples', 1, 10], ['Consultas múltiples', 11, 20], ['Subconsultas', 21, 30]].forEach(([t, a, b]) => {
    add(H4(t));
    BD.consultas.filter(q => q.id.startsWith('Q') && +q.id.slice(1) >= a && +q.id.slice(1) <= b).forEach(q => {
        add(new d.Paragraph({ keepNext: true, spacing: { before: 200 }, children: W.runs(`**${q.id}.** *${q.titulo}*`) }), codigo(q.sql),
            new d.Paragraph({ keepNext: true, children: W.runs(`Resultado: ${q.total} fila(s)${q.total > 6 ? ' (se muestran 6)' : ''}.`, { size: 20 }) }),
            q.ok ? resultado(q.columnas, q.filas) : P('Error: ' + q.error));
    });
});
add(H3('Procedimientos almacenados'));
add(P('Las operaciones que tocan varias tablas se programaron como funciones de PostgreSQL que se ejecutan en una sola transacción: si algo falla, no se guarda nada. Cada una se probó en la base publicada y luego se revirtió.'));
add(tabla('Procedimientos almacenados', ['Código', 'Función', 'Qué hace'], [
    ['PA1', 'fn_registrar_venta', 'Registra la venta, su detalle y descuenta el stock'], ['PA2', 'fn_anular_venta', 'Anula con motivo y devuelve el stock'],
    ['PA3', 'fn_registrar_compra', 'Registra la compra y sube stock y costo promedio'], ['PA4', 'fn_anular_compra', 'Anula la compra y revierte el stock'],
    ['PA5', 'fn_reservar_clase', 'Reserva un lugar validando cupo y fecha'], ['PA6', 'fn_generar_sesiones', 'Crea las clases del calendario sin duplicar'],
    ['PA7', 'sp_cerrar_caja', 'Cierra la caja y calcula el arqueo'], ['PA8', 'fn_crear_pedido', 'Crea el pedido en línea y reserva el stock 30 minutos'],
    ['PA9', 'fn_registrar_pago', 'Registra el pago y marca el pedido como pagado'], ['PA10', 'fn_verificar_pago_qr', 'El taller confirma o rechaza un pago por QR'],
    ['PA11', 'fn_cancelar_pedido', 'Cancela y devuelve el stock'], ['PA12', 'fn_vencer_pedidos', 'Cancela los pedidos no pagados a tiempo'],
], null, { anchos: [10, 30, 60], mono: [1] }));
BD.procedimientos.forEach(pa => {
    add(H4(`${pa.id} ${pa.nombre}`), P(`Prueba: ${pa.descripcion}.`, { sinSangria: true }), codigo(pa.sql),
        pa.ok ? resultado(pa.columnas, pa.filas) : P('Error: ' + pa.error));
});
add(P('PA10 y PA12 se prueban en el flujo completo de la tienda (Anexo C): la verificación del QR por el personal y el vencimiento automático de pedidos.'));
add(H3('Triggers (disparadores)'));
add(P('Los triggers garantizan las reglas del negocio dentro de la base de datos, aunque alguien intente saltarse la aplicación. Cada prueba intenta violar una regla; el resultado esperado es que la base la rechace con un mensaje claro.'));
add(tabla('Prueba de triggers y restricciones', ['Regla', 'Intento', 'Respuesta real de la base'],
    BD.triggers.map(t => [t.regla, t.descripcion, t.ok ? 'Aceptado: ' + JSON.stringify(t.filas[0]) : t.error]),
    'Ejecutado contra la base publicada con el usuario de la aplicación; cada prueba se revirtió. En RN08 la venta se acepta pero con el precio real de la pieza (Bs 95), no con el enviado.', { anchos: [26, 30, 44], tam: 18 }));
const s02 = SQL('02_reglas_negocio.sql');
const it = s02.indexOf('create or replace function tg_reserva_validar');
if (it >= 0) {
    add(P('Ejemplo: trigger que valida una reserva (bloquea la clase y revisa cupo y fecha):', { sinSangria: true }));
    add(codigo(s02.slice(it, s02.indexOf('$$;', it) + 3)));
}
add(H2('5.3 Diseñar casos de uso: diagramas de secuencia'));
add(P('Los diagramas de secuencia muestran, en orden temporal, los mensajes entre el actor, la interfaz, el controlador y las entidades, con los fragmentos alt (alternativa), opt (opcional) y loop (repetición). Los nombres de controladores y métodos son los del código fuente.'));
seccion(true);
CICLOS.forEach(([c, t]) => {
    add(H3(t));
    deCiclo(c).forEach(x => add(figura(`Diagrama de secuencia ${x.id} ${x.nombre}`, DIAG(`sec_${x.id}`), NOTA_FUENTE, { alto: 510 })));
});
seccion();

// ===========================================================================
// CAPÍTULO 6. IMPLEMENTACIÓN
// ===========================================================================
add(H1('Capítulo 6. Flujo de trabajo: implementación'));
add(H2('6.1 Elección de la plataforma de desarrollo del software'));
add(tabla('Plataforma elegida', ['Elemento', 'Elección', 'Motivo'], [
    ['Lenguaje de programación', 'PHP 8.3 sin framework (MVC propio)', 'Conocido por el equipo, gratuito y soportado en Vercel; sin framework se ve y se explica cada capa.'],
    ['Base de datos', 'PostgreSQL 17 (Supabase)', 'Triggers, funciones PL/pgSQL, restricciones EXCLUDE para horarios y seguridad por filas.'],
    ['Sistema operativo', 'Linux en la nube (Vercel y Supabase); Windows 11 en desarrollo', 'No hay que administrar servidores.'],
    ['Interfaz', 'HTML5, CSS3 y JavaScript propio', 'Rápida, adaptable al celular, con modo claro y oscuro.'],
    ['Otros', 'Git y GitHub, Puppeteer, PDO pdo_pgsql', 'Versiones, pruebas visuales y acceso seguro a datos.'],
], null, { anchos: [22, 33, 45] }));
add(H3('Estructura de carpetas'));
add(codigo(`Taller-Mitsu_Proyecto Final/
├── api/index.php        Front controller: todas las rutas y sus permisos
├── app/core/            Auth, Session, Csrf, Validator, View, Mailer, helpers
├── app/controllers/     Un controlador por grupo de casos de uso
├── app/models/          Un modelo por entidad (consultas con parámetros)
├── app/services/        Pasarela de pago
├── app/views/           Vistas por paquete (P1_Seguridad ... P4_Ventas, Tienda)
├── public/              CSS, JavaScript e imágenes
├── Bakend/database/     Scripts SQL 01 a 07 y ejecutor
├── Docs/                Informe, diagramas, capturas, pruebas y guion
└── vercel.json          Configuración del despliegue`));
add(H2('6.2 Diagrama de componentes principal del sistema'));
add(figura('Diagrama de componentes principal', DIAG('componentes'), NOTA_FUENTE));
add(H2('6.3 Diagrama de componentes de cada paquete'));
Object.entries(PAQUETES).forEach(([p, n]) => add(figura(`Componentes de ${n}`, DIAG(`componentes_${p}`), NOTA_FUENTE, { alto: 600 })));
add(H3('Seguridad implementada'));
add(L([
    'Contraseñas cifradas con bcrypt; bloqueo de 10 minutos tras 5 intentos fallidos desde la misma IP.',
    'Sesiones guardadas en la base de datos, con cookie HttpOnly, Secure y SameSite.',
    'Token CSRF en todos los formularios y escape de toda salida HTML.',
    'Cada ruta exige un permiso que se verifica en el servidor; una maestra no puede abrir la clase de otra (403).',
    'Consultas siempre con parámetros (sin concatenar datos del usuario).',
    'Usuario de base de datos de mínimo privilegio, seguridad por filas y triggers como última defensa.',
    'De las tarjetas solo se guardan marca y últimos 4 dígitos; nunca el número completo ni el CVV.',
    'Las claves reales están en variables de entorno de Vercel y en un archivo .env ignorado por Git.',
]));

// ===========================================================================
// 7–9. CONCLUSIONES, RECOMENDACIONES, BIBLIOGRAFÍA
// ===========================================================================
add(H1('7. Conclusiones'));
add(L([
    'Se capturaron los requisitos del taller con una entrevista, un análisis FODA y el método de Ishikawa, y se tradujeron en 22 casos de uso distribuidos en tres ciclos del PUDS.',
    'El modelo de análisis (comunicación y clases de análisis) y el de diseño (paquetes, capas, despliegue, secuencia y componentes) se mantuvieron consistentes con el código: los controladores y métodos de los diagramas existen en el sistema.',
    `La base de datos tiene ${Object.keys(ESQUEMA).length} tablas normalizadas hasta 3FN, con reglas críticas en triggers y procedimientos que se probaron contra la base publicada: las 30 consultas, 10 procedimientos y 11 pruebas de reglas dieron el resultado esperado.`,
    'El sistema está publicado en internet y funciona en computadora y celular: la tienda se ve primero, el cliente ingresa solo para pagar y puede hacerlo con QR, tarjeta o PayPal en modo de demostración.',
    'Los problemas P1 a P7 de la lista final quedan atendidos por los ciclos I a III; la cafetería, las mensualidades y los reportes se abordarán en el ciclo IV.',
]));
add(H1('8. Recomendaciones'));
add(L([
    'Pasar la base a un plan pagado de Supabase antes de usarla en el taller: el plan gratuito se pausa tras 7 días sin uso.',
    'Configurar el correo SMTP de Gmail con verificación en dos pasos y contraseña de aplicación para enviar comprobantes y códigos de recuperación.',
    'Antes de cobrar de verdad, contratar una pasarela autorizada en Bolivia (QR bancario con conciliación automática) o una cuenta comercial de PayPal.',
    'Capacitar a recepción y maestras con el manual del Anexo D y cambiar todas las contraseñas de demostración.',
    'Implementar el ciclo IV (cafetería, mensualidades y reportes) y medir los beneficios estimados en el capítulo 5 con datos reales.',
]));
add(H1('9. Bibliografía'));
[
    'American Psychological Association. (2020). *Publication manual of the American Psychological Association* (7th ed.). https://doi.org/10.1037/0000165-000',
    'Booch, G., Rumbaugh, J., y Jacobson, I. (2006). *El lenguaje unificado de modelado: Guía del usuario* (2.ª ed.). Pearson Educación.',
    'Elmasri, R., y Navathe, S. B. (2016). *Fundamentals of database systems* (7th ed.). Pearson.',
    'Ishikawa, K. (1985). *What is total quality control? The Japanese way*. Prentice Hall.',
    'Jacobson, I., Booch, G., y Rumbaugh, J. (2000). *El proceso unificado de desarrollo de software*. Addison Wesley.',
    'Larman, C. (2004). *UML y patrones: Una introducción al análisis y diseño orientado a objetos y al proceso unificado* (2.ª ed.). Pearson Educación.',
    'Laudon, K. C., y Laudon, J. P. (2020). *Management information systems: Managing the digital firm* (16th ed.). Pearson.',
    'Luhn, H. P. (1960). *Computer for verifying numbers* (U.S. Patent No. 2,950,048). U.S. Patent and Trademark Office.',
    'Mell, P., y Grance, T. (2011). *The NIST definition of cloud computing* (NIST Special Publication 800-145). National Institute of Standards and Technology. https://doi.org/10.6028/NIST.SP.800-145',
    'OWASP Foundation. (2021). *OWASP Top 10: 2021*. https://owasp.org/Top10/',
    'PCI Security Standards Council. (2022). *Payment Card Industry Data Security Standard* (v4.0). https://www.pcisecuritystandards.org/',
    'PHP Group. (2024). *PHP manual*. https://www.php.net/manual/es/',
    'PostgreSQL Global Development Group. (2024). *PostgreSQL 17 documentation*. https://www.postgresql.org/docs/17/',
    'Pressman, R. S., y Maxim, B. R. (2020). *Software engineering: A practitioner’s approach* (9th ed.). McGraw-Hill Education.',
    'Sommerville, I. (2016). *Software engineering* (10th ed.). Pearson.',
    'Supabase. (2025). *Supabase documentation*. https://supabase.com/docs',
    'Vercel. (2025). *Vercel documentation*. https://vercel.com/docs',
].forEach(r => add(ref(r)));

// ===========================================================================
// ANEXOS
// ===========================================================================
add(H1('Anexos'));
add(H2('Anexo A. Entrevista a la gerente'));
add(tabla('Datos de la entrevista', ['Campo', 'Dato'], [
    ['Lugar', 'Taller de Cerámica Mitsü, Santa Cruz de la Sierra'], ['Duración', '22 minutos'], ['Fecha', 'No consta en el registro original del grupo'],
    ['Entrevistada', 'Alessandra Civardi Soria, gerente'], ['Entrevistador', 'Mauricio Miranda Soria'], ['Objetivo', 'Conocer los procesos actuales y las necesidades del taller'],
], 'Transcripción tomada del informe de perfil anterior del grupo.', { anchos: [25, 75] }));
[
    ['¿Cuáles fueron los inicios del taller?', 'Luego de terminar sus estudios en Argentina, y con la ayuda de su amiga Daniela, surgió la idea de crear un taller de cerámica para poner en práctica lo aprendido en la carrera de Artes Plásticas. Al ver lo difícil que era conseguir arcilla localmente y realizar los procesos de fabricación, decidieron fundar el taller para cubrir esa necesidad.'],
    ['¿Cuáles fueron sus inspiraciones para la estética de sus piezas?', 'Lo aprendido en la universidad en Argentina sobre diseños contemporáneos, fusionado con la esencia de los pueblos originarios de Bolivia (región andina de La Paz y Amazonía boliviana). No se limitan a estas temáticas; buscan expandir sus horizontes con cada proyecto, apostando por una fabricación artesanal con materiales originarios del país.'],
    ['¿En qué productos se enfocan más al momento de crear?', 'En vajillas, tazas y platos, con un enfoque utilitario o más artístico según los pedidos de los clientes.'],
    ['¿Cuántas maestras dan clases y qué horarios manejan?', 'Son 4 maestras. Martes: modelado a mano y avanzado (10:00–12:00) y modelado (18:00–20:00). Miércoles: modelado a mano y torno (10:00–12:00) y taller de modelado (18:00–20:00). Jueves y viernes: modelado a mano y torno (10:00–12:00). Sábado: adultos (10:00–12:00), niños (16:00–18:00) y modelado y torno (18:00–20:00).'],
    ['¿Qué es lo más solicitado, las clases o las piezas terminadas?', 'Las clases, porque las piezas terminadas tienen un costo más elevado por el trabajo artesanal.'],
    ['¿Piensan ampliar el personal del taller?', 'Por el momento no, por la situación económica del país. A futuro sería ideal contratar personal dedicado solo a la producción.'],
    ['¿Cómo consiguen la arcilla y los materiales?', 'Los compran a empresas mineras de Cochabamba y La Paz, que son sus únicos proveedores de arcilla y minerales como el cuarzo.'],
    ['¿Cómo organizan sus clases y la administración? ¿Usan algún sistema?', 'Usan hojas de cálculo de Excel para los horarios y un pequeño software de gestión para sus finanzas.'],
    ['¿Ofrecen otro servicio aparte de las clases y la venta?', 'Sí, una cafetería para alumnos y visitantes, con personal propio dedicado a la atención gastronómica.'],
    ['¿Qué ingredientes usan en la cafetería?', 'Mayormente naturales y de cultivo propio, para un menú saludable y delicioso.'],
].forEach(([q, r], i) => add(new d.Paragraph({ keepNext: true, spacing: { before: 160 }, children: W.runs(`**${i + 1}. ${q}**`) }), P(r)));
add(H2('Anexo B. Cuentas de prueba'));
add(tabla('Cuentas de demostración', ['Rol', 'Correo', 'Contraseña'], [
    ['Administrador', 'admin@mitsu.test', 'Admin2026!'], ['Administrativo', 'ventas@mitsu.test', 'Ventas2026!'], ['Maestra', 'maestra@mitsu.test', 'Maestra2026!'],
    ['Alumno', 'alumno@mitsu.test', 'Alumno2026!'], ['Cliente', 'cliente@mitsu.test', 'Cliente2026!'],
], 'Contraseñas inventadas solo para la demostración. Tarjetas de prueba: 4242 4242 4242 4242 (aprobada) y 4000 0000 0000 0002 (rechazada), con cualquier fecha futura y CVV de 3 dígitos.', { anchos: [25, 40, 35], mono: [1, 2] }));
add(H2('Anexo C. Pruebas del sistema'));
add(tabla('Pruebas automáticas', ['Script', 'Qué prueba', 'Resultado'], [
    ['Docs/pruebas/probar_rutas.php', '55 pantallas con cada rol; 20 accesos prohibidos (403); página inexistente (404); formulario sin token (419).', 'Todo correcto'],
    ['Docs/pruebas/probar_flujos.php', '20 operaciones de escritura: usuarios, roles, talleres, reservas, asistencia, compras, ventas, anulaciones y caja.', 'Todo correcto'],
    ['Docs/pruebas/probar_tienda.php', 'Unas 30 verificaciones de la tienda: carrito sin cuenta, ingreso que vuelve al pago, Luhn, tarjeta vencida, rechazada y aprobada, cobro doble, PayPal, QR verificado por el taller, entrega y cancelación con devolución de stock, y seguridad del cliente.', 'Todo correcto'],
    ['Docs/informe/resultados_bd.php', '30 consultas, 10 procedimientos y 11 pruebas de reglas contra la base publicada.', '0 errores'],
], 'Los scripts se ejecutaron en local y contra el sistema publicado durante el desarrollo de los ciclos I a III.', { anchos: [30, 52, 18], mono: [0] }));
add(H2('Anexo D. Manual de uso'));
[
    ['Visitante y cliente', ['Entre a la página principal: verá las piezas y las clases.', 'Abra una pieza y presione “Agregar al carrito”.', 'En el carrito presione “Pagar”. Si no tiene cuenta, créela con su correo; el sistema lo devuelve al carrito.', 'Elija retiro en el taller o envío, escriba su teléfono y confirme: las piezas quedan reservadas 30 minutos.', 'Pague con el QR del taller (escriba el número de comprobante), con tarjeta o con PayPal. Verá su comprobante en “Mis pedidos”.']],
    ['Alumno', ['Cree su cuenta en “Crea tu cuenta de alumno” (si es menor, con datos del tutor).', 'En “Reservar clase” elija una clase con lugares libres y presione “Reservar mi lugar”.', 'Puede cancelar una reserva hasta 12 horas antes desde su inicio.']],
    ['Maestra', ['Ingrese con su correo: verá sus clases de la semana.', 'Abra la clase del día, marque “Presente” o “Falta” en cada alumno y guarde.']],
    ['Administrativo', ['Abra la caja al iniciar el día con el monto inicial.', 'En “Vender” toque las piezas, elija cliente y método de pago, escriba el monto recibido y presione “Cobrar y emitir nota”.', 'Registre compras en “Compras → Nueva compra”; el stock sube solo.', 'Registre consumo o merma desde el kardex de cada material.', 'En “Pedidos web” confirme los pagos por QR y marque los pedidos entregados.', 'Al cerrar, cuente el efectivo y cierre la caja: el sistema muestra la diferencia.']],
    ['Administrador', ['Cree usuarios y asígneles un rol en “Usuarios”.', 'Ajuste qué puede hacer cada rol en “Roles y permisos”.', 'Registre maestras, talleres y horarios; genere el calendario.', 'Revise la bitácora y anule ventas con motivo cuando corresponda.']],
].forEach(([rol, pasos]) => add(H3(rol), L(pasos, true)));
add(H2('Anexo E. Script completo de la base de datos'));
add(P('Se incluyen los archivos que crean la estructura, las reglas, el usuario de la aplicación, los procedimientos y la tienda. El archivo de datos de demostración (03) y el de consultas (05) están en el repositorio.'));
['01_esquema.sql', '02_reglas_negocio.sql', '04_usuario_app.sql', '06_procedimientos_triggers.sql', '07_tienda_pasarela.sql'].forEach(f => add(H3(f), codigo(SQL(f), 15)));

// ===========================================================================
// DOCUMENTO
// ===========================================================================
const estiloTitulo = (id, nombre, op) => ({
    id, name: nombre, basedOn: 'Normal', next: 'Normal', quickFormat: true,
    run: { font: W.FUENTE, size: 24, bold: true, italics: !!op.cursiva, color: '000000' },
    paragraph: { alignment: op.centro ? d.AlignmentType.CENTER : d.AlignmentType.LEFT, spacing: { before: op.antes ?? 240, after: 120, line: 360 }, keepNext: true, keepLines: true, outlineLevel: op.nivel },
});
const doc = new d.Document({
    creator: 'Grupo Mitsü', title: 'Sistema de Información para la Gestión Integral del Taller de Cerámica Mitsü', description: 'Informe final ciclos I a III',
    features: { updateFields: true },
    styles: {
        default: { document: { run: { font: W.FUENTE, size: 24 } } },
        paragraphStyles: [
            estiloTitulo('Heading1', 'Heading 1', { centro: true, nivel: 0, antes: 0 }),
            estiloTitulo('Heading2', 'Heading 2', { nivel: 1 }),
            estiloTitulo('Heading3', 'Heading 3', { cursiva: true, nivel: 2 }),
            estiloTitulo('Heading4', 'Heading 4', { cursiva: true, nivel: 3, antes: 160 }),
        ],
    },
    sections: secciones,
});
const salida = path.join(AQUI, 'Informe_Taller_Mitsu_Ciclos_I-III.docx');
d.Packer.toBuffer(doc).then(b => {
    fs.writeFileSync(salida, b);
    console.log('Informe generado:', path.basename(salida), Math.round(b.length / 1024), 'KB');
});
