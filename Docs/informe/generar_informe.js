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
const { d, H1, H1s, H2, H3, H4, H4i, P, L, salto, vacio, figura, tabla, ficha, codigo, resultado, ref, horizontal } = W;

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
const GRUPO = 'Grupo 4';
const INTEGRANTES = ['Baqueros Hurtado Marco Antonio Junior', 'Estaca Paco Sergio', 'Miranda Soria Mauricio', 'Santeyana Durán Sarah Nicol'];
const CICLOS = [['I', 'Ciclo I: seguridad y configuración'], ['II', 'Ciclo II: clases y reservas'], ['III', 'Ciclo III: inventario, ventas y tienda en línea']];
const deCiclo = c => CASOS.filter(x => x.ciclo === c);
const NOTA_FUENTE = 'Elaboración propia.';
const { ROLES, CASOS: COMPLEMENTO } = require('./complemento_casos');

/** Estructura de carpetas del repositorio tal como está en Git (git ls-files). */
function arbolGit() {
    const NOTAS = {
        'Bakend': 'Lógica del sistema y base de datos', 'Bakend/app': 'bootstrap.php: arranque de la aplicación',
        'Bakend/app/controllers': 'Un controlador por grupo de casos de uso', 'Bakend/app/core': 'Núcleo: Router, Auth, Validator, Mailer',
        'Bakend/app/models': 'Un modelo por entidad (consultas con parámetros)', 'Bakend/app/services': 'Pasarela de pago',
        'Bakend/database': 'Scripts SQL 01 a 07 y ejecutar.php', 'Frontend': 'Lo que ve el usuario', 'Frontend/views': 'Pantallas por paquete',
        'Frontend/public': 'Archivos que descarga el navegador', 'Frontend/public/assets': 'CSS, JavaScript e imágenes', 'Docs': 'Documentación del proyecto',
        'Docs/diagramas': 'Diagramas UML (Python) en svg y png', 'Docs/informe': 'Este informe y sus generadores', 'Docs/pruebas': 'Pruebas automáticas',
        'api': 'Vercel exige esta carpeta', 'api/index.php': 'Controlador frontal: entrada de todas las rutas',
        'router.php': 'Servidor local de PHP', '.htaccess': 'Apache (Laragon o XAMPP)', 'vercel.json': 'Configuración del despliegue',
    };
    const archivos = require('child_process').execSync('git ls-files', { cwd: RAIZ, encoding: 'utf8' }).split('\n').filter(Boolean);
    const hijos = new Map([['', new Set()]]);
    const agregar = (padre, nombre) => { if (!hijos.has(padre)) hijos.set(padre, new Set()); hijos.get(padre).add(nombre); };
    archivos.forEach(f => {
        const partes = f.split('/');
        const carpetas = partes.slice(0, -1);
        carpetas.slice(0, 3).forEach((c, i) => agregar(carpetas.slice(0, i).join('/'), c + '/'));
        if (carpetas.length === 0 || (carpetas.length === 1 && carpetas[0] === 'api')) agregar(carpetas.join('/'), partes[partes.length - 1]);
    });
    const lineas = [path.basename(RAIZ) + '/'];
    const dibujar = (ruta, prefijo) => {
        const lista = [...(hijos.get(ruta) || [])].sort((x, y) => (y.endsWith('/') - x.endsWith('/')) || x.localeCompare(y));
        lista.forEach((n, i) => {
            const ultimo = i === lista.length - 1;
            const completa = (ruta ? ruta + '/' : '') + n.replace(/\/$/, '');
            const izquierda = prefijo + (ultimo ? '└── ' : '├── ') + n;
            lineas.push(NOTAS[completa] ? izquierda.padEnd(38) + NOTAS[completa] : izquierda);
            if (n.endsWith('/')) dibujar(completa, prefijo + (ultimo ? '    ' : '│   '));
        });
    };
    dibujar('', '');
    return lineas.join('\n');
}

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
// CARÁTULA
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
    dato('Grupo', GRUPO),
    dato('Integrantes', ''),
    ...INTEGRANTES.map(n => new d.Paragraph({ indent: { left: 2160 }, spacing: { after: 60 }, children: [new d.TextRun({ text: '• ' + n, font: W.FUENTE })] })),
    new d.Paragraph({ spacing: { before: 600 }, alignment: d.AlignmentType.CENTER, children: [new d.TextRun({ text: 'Santa Cruz de la Sierra – Bolivia', font: W.FUENTE, bold: true })] }),
    centro('Octubre de 2026', { negrita: true }),
);
// ===========================================================================
// ÍNDICE
// ===========================================================================
seccion();
add(
    new d.Paragraph({ alignment: d.AlignmentType.CENTER, spacing: { after: 240 }, children: [new d.TextRun({ text: 'Índice', bold: true, font: W.FUENTE })] }),
    new d.TableOfContents('Índice', { hyperlink: true, headingStyleRange: '1-4' }),
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
add(H4('Análisis FODA'));
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
add(H1('2. Elementos del sistema en computadoras'));
add(H2('2.1 Hardware'));
add(H3('2.1.1 Servidor'));
add(P('El sistema no necesita un servidor propio en el taller: se ejecuta en la nube. La aplicación PHP corre en Vercel como función sin servidor (región gru1, São Paulo) y la base de datos PostgreSQL está en Supabase (región sa-east-1). Ambos servicios escalan solos y se pagan por uso; en su plan gratuito alcanzan para el volumen del taller.'));
add(H3('2.1.2 Cliente'));
add(P('Cualquier computadora, tableta o celular con un navegador actualizado. Para la recepción y el punto de venta se recomienda la siguiente configuración:'));
add(tabla('Configuración recomendada del equipo cliente', ['Nombre', 'Versión', 'Características'], [
    ['Procesador', 'Intel Core i3 o AMD Ryzen 3 (o superior)', '2 núcleos o más'], ['Memoria RAM', 'DDR4', '4 GB o más'],
    ['Disco', 'SSD', '128 GB o más'], ['Monitor', '13 pulgadas o más', 'Resolución 1366 × 768 o mayor'],
    ['Celular (maestras y alumnos)', 'Android 9 o iOS 14 en adelante', 'Navegador actualizado'],
], 'Configuración mínima estimada por el equipo; el sistema se probó también en celular.', { anchos: [28, 40, 32] }));
add(H3('2.1.3 Medios de comunicación'));
add(P('Conexión a internet de banda ancha (se recomienda 10 Mbps o más) por Wi-Fi o cable. Todo el tráfico viaja cifrado por HTTPS; la conexión de la aplicación a la base de datos usa TLS.'));
add(H3('2.1.4 Otros dispositivos'));
add(tabla('Otros dispositivos', ['Nombre', 'Características'], [
    ['Impresora térmica o común', 'Imprime la nota de venta y los comprobantes.'], ['Celular de la maestra', 'Registra la asistencia desde la clase.'],
    ['Celular del cliente', 'Escanea el QR del taller para pagar los pedidos en línea.'], ['Cámara o celular', 'Toma las fotos de las piezas para la tienda.'],
], null, { anchos: [35, 65] }));
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
add(tabla('Software del lado del cliente', ['Nombre', 'Versión'], [
    ['Sistema operativo', 'Windows 10/11, macOS, Android o iOS'], ['Navegador', 'Chrome, Edge, Firefox o Safari (versión actual)'],
    ['Interfaz', 'HTML5, CSS3 y JavaScript propio, modo claro y oscuro, adaptable al celular'],
], null, { anchos: [35, 65] }));
add(H3('2.2.3 Otro software adicional'));
add(tabla('Otro software adicional', ['Nombre', 'Descripción'], [
    ['Git y GitHub', 'Control de versiones y repositorio público.'], ['Visual Studio Code', 'Editor de código.'],
    ['Generador UML propio (Python)', 'Diagramas UML en notación de Enterprise Architect, generados desde la especificación de los casos de uso.'],
    ['LibreOffice y librería docx', 'Armado del informe y exportación a PDF.'], ['Puppeteer', 'Capturas de pantalla y pruebas en celular.'],
], null, { anchos: [35, 65] }));
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
add(P('Cuatro computadoras portátiles de los integrantes (procesador de 4 núcleos, 8 GB de RAM o más) y conexión a internet. No se compró hardware.'));

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
add(P('Todo el software usado es libre o gratuito (ver la tabla de herramientas de software en 3.3.1), por lo que el costo de licencias es **Bs 0**.'));
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
add(P('El Taller de Cerámica Mitsü genera ingresos con tres líneas de negocio. La principal son las **clases** de modelado a mano y torno para niños y adultos, que dictan cuatro maestras de martes a sábado; según la gerente, es lo más solicitado porque las piezas terminadas cuestan más por el trabajo artesanal. La segunda es la **venta de piezas** de cerámica hechas a mano (vajillas, tazas y platos), de uso utilitario o artístico según el pedido del cliente. La tercera es la **cafetería** para alumnos y visitantes, atendida por personal propio y con ingredientes mayormente de cultivo propio.'));
add(P('Para producir, el taller compra arcilla y minerales como el cuarzo a empresas mineras de Cochabamba y La Paz, que son sus únicos proveedores. Sus clientes son los alumnos que se inscriben a los talleres, las personas que compran piezas y quienes visitan la cafetería. La propuesta de valor es una cerámica artesanal con materiales del país, que fusiona el diseño contemporáneo con la estética de los pueblos originarios de Bolivia.'));
add(P('Hoy la gestión es manual: los horarios se llevan en hojas de Excel y las finanzas en un pequeño software aparte, sin conexión entre sí. El flujo de trabajo actual de reservas, clases, ventas y cierre se modela con el diagrama de actividad del capítulo 1 (punto 5.1), donde se analiza junto con el diagrama de Ishikawa.'));
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
teoria.forEach(([t, x]) => add(H4(t), P(x)));

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
    ['No existe un canal para mostrar y vender las piezas en línea.', 'Ventas'],
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
    ['22', 'Mantener', 'Problema propio de la venta de piezas: los clientes solo pueden comprar en el local.'],
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
add(P('El árbol de problemas muestra cómo los veintidós problemas de la lista inicial se agrupan en los siete problemas de la lista final y todos llevan al problema central: la gestión manual y desintegrada del taller. Los problemas de la cafetería, las mensualidades y los pedidos personalizados quedan aparte, postergados al ciclo IV.'));
seccion(true);
add(figura('Árbol de problemas del Taller Mitsü', DIAG('arbol_problemas'), 'Elaboración propia a partir de la lista inicial de problemas y su depuración.', { alto: 460 }));
seccion();
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
add(P('El diagrama se arma sobre un eje horizontal que termina en el efecto, es decir, el problema central que se quiere explicar: la gestión manual y desintegrada de las clases, el inventario y las ventas del Taller Mitsü. Sobre este eje se agregan después las categorías y las causas.'));
add(figura('Eje y efecto del diagrama de Ishikawa', DIAG('ishikawa_eje'), NOTA_FUENTE, { alto: 230 }));
add(H2('2. Identificar las principales categorías'));
add(P('Para el análisis del problema se identificaron seis categorías principales, que representan las áreas críticas del funcionamiento del taller y forman las espinas del diagrama.'));
seccion(true);
add(figura('Categorías principales del diagrama de Ishikawa', DIAG('ishikawa_categorias'), NOTA_FUENTE, { alto: 470 }));
seccion();
[
    ['C1. Métodos y procesos', 'Agrupa la forma en que hoy se hacen las tareas del día a día: llevar los horarios y las reservas de las clases, anotar las ventas de piezas y cerrar la caja. Los procesos relacionados son la reserva de clases, la venta en caja y el arqueo diario.'],
    ['C2. Personas', 'Se refiere al personal del taller y a cómo se reparten las tareas. El taller no puede contratar más personal, así que recepción y las maestras cargan con el trabajo manual. Los procesos relacionados son la asignación de maestras a los horarios y la atención de las reservas.'],
    ['C3. Tecnología', 'Abarca las herramientas que usa el taller para registrar su información: hojas de Excel y un pequeño software de finanzas, sin conexión entre sí. Los procesos relacionados son el registro de datos, el control de acceso y el respaldo de la información.'],
    ['C4. Materiales e inventario', 'Se centra en la arcilla, los esmaltes y los minerales que se usan para producir las piezas y dictar las clases. Los procesos relacionados son el control de existencias, las compras a proveedores y el registro del consumo.'],
    ['C5. Medición y control', 'Reúne la información que la gerencia necesita para controlar el taller y decidir: historial de asistencia, ventas, precios y costos. Los procesos relacionados son el registro de asistencia, el cierre de caja y la consulta de historiales.'],
    ['C6. Clientes y entorno', 'Trata la relación con los alumnos y los compradores, y la competencia de otros talleres. Los procesos relacionados son la consulta de clases disponibles, la venta de piezas y el pago.'],
].forEach(([c, t]) => add(new d.Paragraph({ spacing: { line: 480 }, children: W.runs(`**${c}:** ${t}`) })));
add(H2('3. Identificar las causas'));
add(P('A partir de las categorías definidas y de la lista final de problemas se identificaron tres causas por categoría, que se ubican como espinas secundarias del diagrama.'));
seccion(true);
add(figura('Diagrama de Ishikawa del Taller Mitsü', DIAG('ishikawa'), 'Elaboración propia a partir de la entrevista (Anexo A) y de la lista final de problemas.', { alto: 470 }));
seccion();
const CAUSAS = [
    ['C1. Métodos y procesos', 'Los horarios y las reservas se llevan en hojas de Excel, las ventas se anotan a mano sin nota numerada y no hay cierre de caja ni arqueo diario.'],
    ['C2. Personas', 'El personal es reducido y no se puede contratar más, las maestras se asignan sin un registro central y recepción responde las reservas una por una.'],
    ['C3. Tecnología', 'El Excel y el software de finanzas no están integrados, no existe una página para ver las piezas o las clases y no hay respaldos ni control de acceso.'],
    ['C4. Materiales e inventario', 'El stock de arcilla y minerales no tiene registro, se hacen compras urgentes por falta de material y el taller depende de solo dos proveedores, de Cochabamba y La Paz.'],
    ['C5. Medición y control', 'No hay historial de asistencia de los alumnos, no existen reportes de ventas ni indicadores y los precios y costos no guardan historial.'],
    ['C6. Clientes y entorno', 'Los alumnos no ven los cupos en tiempo real, los clientes no pueden comprar ni pagar en línea y existe competencia de otros talleres de la ciudad.'],
];
CAUSAS.forEach(([c, t]) => add(new d.Paragraph({ keepNext: true, spacing: { before: 120 }, children: W.runs(`**${c}**`) }), P(t, { sinSangria: true })));
add(H2('4. Analizar y discutir el diagrama'));
add(P('Las categorías con más peso son **métodos y procesos** y **tecnología**: casi todos los problemas nacen de anotar en Excel o en papel lo que debería registrarse una sola vez. Para cada categoría se resumen los problemas que reúne y la solución que propone el sistema, indicando los casos de uso que la implementan.'));
[
    ['C1. Métodos y procesos', ['P1. Horarios y reservas manuales, sin cupos en tiempo real.', 'P4. Ventas sin registro, sin nota numerada y sin cierre de caja.'],
     'Registrar los horarios y generar el calendario de clases (CU09), reservar con control de cupo (CU10), vender en caja con nota de venta numerada (CU18) y abrir y cerrar la caja con arqueo (CU19). Cada dato se ingresa una sola vez y el resto se calcula.'],
    ['C2. Personas', ['P1. Maestras asignadas sin registro central y reservas atendidas una por una.'],
     'Registrar las maestras y asignarlas a los horarios sin cruces (CU07 y CU09) y dar a los alumnos un portal para reservar por su cuenta (CU12), de modo que el personal reducido no tenga que atender cada reserva.'],
    ['C3. Tecnología', ['P5. Información dispersa en herramientas no integradas y sin control de acceso.', 'P6. Sin página para ver o comprar las piezas.', 'P7. Riesgo de pérdida de datos sin respaldos.'],
     'Un solo sistema web en la nube con usuarios, roles y permisos (CU01 a CU04), bitácora de operaciones (CU05) y configuración centralizada (CU06), más la tienda en línea para ver y comprar las piezas (CU20). La base de datos en la nube elimina los archivos en computadoras personales.'],
    ['C4. Materiales e inventario', ['P3. Inventario de materiales sin registro, faltantes y compras urgentes.'],
     'Registrar los materiales con stock mínimo (CU13), los proveedores (CU14) y las compras (CU15), y el consumo y la merma en el kardex con alertas de stock bajo (CU16). La dependencia de dos proveedores es una condición del entorno que el sistema no cambia, pero el registro de compras permite planificarlas.'],
    ['C5. Medición y control', ['P2. Sin historial de asistencia de los alumnos.', 'P4. Sin reportes de ventas ni historial de precios.'],
     'Registrar la asistencia de cada clase (CU11), guardar el historial de precios de las piezas (CU17) y el listado de ventas con su cierre de caja (CU18 y CU19). Los reportes con indicadores se completan en el ciclo IV (CU28).'],
    ['C6. Clientes y entorno', ['P1. Los alumnos no ven los cupos en tiempo real.', 'P6. Los clientes no pueden comprar ni pagar en línea.'],
     'Portal del alumno con los cupos libres de cada clase (CU10 y CU12) y tienda en línea con carrito, pago por QR, tarjeta o PayPal y gestión de los pedidos web (CU20 a CU22), que además diferencia al taller de su competencia.'],
].forEach(([c, problemas, solucion]) => add(
    new d.Paragraph({ keepNext: true, spacing: { before: 160 }, children: W.runs(`**${c}**`) }),
    new d.Paragraph({ keepNext: true, children: W.runs('**Problemas identificados:**') }),
    L(problemas),
    new d.Paragraph({ keepNext: true, children: W.runs('**Solución propuesta:**') }),
    P(solucion, { sinSangria: true }),
));
add(salto());
add(H2('5. Modelo de negocio'));
add(P('El modelo de negocio describe cómo trabaja el taller y conecta el análisis de Ishikawa con la captura de requisitos. Primero se muestra el proceso actual, donde aparecen las causas identificadas (anotar en papel, revisar el cuaderno, contar el dinero a mano). Luego se detallan los cuatro procesos principales tal como funcionan con el sistema: cada carril es un actor del capítulo 2 y cada actividad del carril «Sistema Mitsü» corresponde a un caso de uso.'));
add(H3('5.1 Diagrama de actividad'));
add(P('El alumno consulta los horarios por WhatsApp y recepción revisa el cuaderno para ver si hay lugar y anotar la reserva. La maestra dicta la clase y anota la asistencia en papel. Las ventas de piezas se anotan a mano y, al cierre, la gerencia cuenta el dinero y revisa el stock para decidir las compras. Estas actividades manuales son las que el sistema reemplaza en los ciclos I a III.'));
add(figura('Diagrama de actividad del proceso actual (modelo de negocio)', DIAG('actividad_negocio'), 'Elaboración propia a partir de la entrevista (Anexo A).', { alto: 600 }));
[
    ['5.1.1 Reserva de clases y registro de asistencia', 'actividad_reservas',
     'El alumno ingresa a su portal y ve las clases de la semana con sus lugares libres. Al pedir un lugar, el sistema verifica que haya cupo y que la clase sea futura; si no se cumple, el alumno ve el aviso y elige otra clase. Si se cumple, el procedimiento fn_reservar_clase registra la reserva. Desde ahí el proceso sigue en paralelo: el alumno asiste a la clase y la maestra abre la lista de su clase del día, marca presente o falta, y el sistema guarda la asistencia en la bitácora. Reemplaza el cuaderno de reservas y la asistencia en papel (causas C1 y C5) y corresponde a los casos de uso CU10, CU11 y CU12.'],
    ['5.1.2 Consumo de materiales y compra a proveedores', 'actividad_inventario',
     'Cuando la maestra usa arcilla, esmaltes o minerales, el administrativo registra el consumo o la merma en el kardex del material y el sistema descuenta el stock. Si el stock queda bajo el mínimo, el sistema muestra la alerta en la pantalla de materiales; el administrativo pide el material al proveedor de Cochabamba o La Paz y, al recibirlo con su factura, registra la compra. El procedimiento fn_registrar_compra sube el stock y recalcula el costo promedio. Resuelve el inventario sin registro y las compras urgentes (causa C4) y corresponde a los casos de uso CU13 a CU16.'],
    ['5.1.3 Venta en caja y cierre de caja', 'actividad_ventas',
     'Al iniciar el día el administrativo abre la caja con el monto inicial. Cuando un cliente elige piezas en el local, el administrativo las agrega con el cliente y el método de pago; el sistema verifica que la caja esté abierta y que haya stock suficiente. Si la venta es válida, fn_registrar_venta la registra, descuenta el stock y emite la nota de venta numerada (NV). Al cierre, el administrativo cuenta el efectivo y sp_cerrar_caja calcula el efectivo esperado y la diferencia, que la gerencia revisa junto con las ventas y la bitácora. Reemplaza las ventas anotadas a mano y el cierre sin arqueo (causas C1 y C5) y corresponde a los casos de uso CU17, CU18 y CU19.'],
    ['5.1.4 Compra en la tienda en línea y pago', 'actividad_tienda',
     'El cliente ve las piezas en la tienda pública, las agrega al carrito y confirma el pedido después de ingresar con su cuenta. El procedimiento fn_crear_pedido crea el pedido y reserva el stock por 30 minutos. El cliente paga con QR, tarjeta o PayPal: si el pago se rechaza puede intentar de nuevo; si se aprueba, el pedido queda pagado con su comprobante; y si avisa que pagó por QR, el administrativo verifica el depósito y lo confirma. Finalmente el administrativo entrega el pedido y el sistema lo marca como entregado. Resuelve la falta de venta en línea (causa C6) y corresponde a los casos de uso CU20, CU21 y CU22.'],
].forEach(([t, f, texto]) => {
    add(H4i(t, true), P(texto));
    add(figura('Diagrama de actividad: ' + t.charAt(6).toLowerCase() + t.slice(7), DIAG(f), 'Elaboración propia a partir del funcionamiento del sistema.', { alto: 430 }));
});

// ===========================================================================
// CAPÍTULO 2. CAPTURA DE REQUISITOS
// ===========================================================================
add(H1('Capítulo 2. Flujo de trabajo: captura de requisitos'));
add(H2('2.1 Identificar actores y casos de uso'));
add(P('Los actores son las personas que interactúan con el sistema. Se identificaron a partir de la entrevista y del modelo de negocio: cada carril de los diagramas de actividad del capítulo 1 es un actor. Para cada uno se describe quién es, qué hace en el sistema y hasta dónde llega su acceso.'));
add(H4('Identificar actores y describir roles'));
ROLES.forEach(([n, , nombre, quien, funciones, rol]) => add(
    new d.Paragraph({ keepNext: true, spacing: { before: 200, after: 40 }, children: W.runs(`**${n}: ${nombre}**`) }),
    new d.Paragraph({ keepNext: true, indent: { left: 360 }, children: W.runs(quien) }),
    new d.Paragraph({ keepNext: true, indent: { left: 360 }, children: W.runs(`**Funcionalidades principales:** ${funciones}`) }),
    new d.Paragraph({ indent: { left: 360 }, children: W.runs(`**Rol en el sistema:** ${rol}`) }),
));
add(P('Todos los actores con cuenta heredan de Usuario el ingreso, el cierre de sesión y la recuperación de contraseña. El Cliente y el Alumno pueden comprar en la tienda; el Administrador puede hacer todo lo del Administrativo.'));
add(figura('Generalización de actores', DIAG('actores'), NOTA_FUENTE, { ancho: 520 }));
add(H4('Identificar casos de uso'));
add(P('Cada caso de uso es un objetivo completo que un actor logra con el sistema. Se identificaron 22 casos de uso para los ciclos I a III, agrupados en cuatro paquetes; los del ciclo IV quedan planificados.'));
add(tabla('Casos de uso identificados', ['ID', 'Caso de uso', 'Actores', 'Ciclo', 'Paquete'],
    CASOS.map(x => [x.id, x.nombre, x.actores.map(a => ACTORES[a]).join(', '), x.ciclo, x.paquete]), null, { anchos: [10, 42, 26, 9, 13] }));
add(tabla('Casos de uso planificados para el ciclo IV', ['ID', 'Caso de uso', 'Paquete'], [
    ['CU23', 'Gestionar menú de cafetería', 'P5'], ['CU24', 'Registrar comandas', 'P5'], ['CU25', 'Cobrar mensualidades y controlar morosidad', 'P6'],
    ['CU26', 'Gestionar pedidos personalizados', 'P6'], ['CU27', 'Enviar notificaciones', 'P7'], ['CU28', 'Generar reportes e indicadores', 'P7'],
], 'No forman parte de esta entrega.', { anchos: [10, 75, 15] }));
add(H2('2.2 Priorizar los casos de uso'));
add(P('La prioridad indica qué tan necesario es el caso de uso para que el taller funcione, y el riesgo, qué tan delicada es su construcción. Dentro de cada ciclo se construyeron primero los de prioridad alta y riesgo alto, porque concentran las reglas más delicadas: cupo, stock y dinero.'));
CICLOS.forEach(([c, t]) => {
    add(H4(t));
    add(tabla(`Priorización del ${t.split(':')[0].toLowerCase()}`, ['Caso de uso', 'Prioridad', 'Riesgo', 'Justificación'],
        deCiclo(c).map(x => [`${x.id}: ${x.nombre}`, x.prioridad, x.riesgo, COMPLEMENTO[x.id][1]]), null, { anchos: [30, 12, 11, 47] }));
});
add(H2('2.3 Detallar un caso de uso'));
add(P('Cada caso de uso se detalla con su diagrama y una ficha: objetivo, resumen, actores, precondición y postcondición, flujo principal paso a paso, flujos secundarios (caminos alternativos dentro del caso) y excepciones (errores que interrumpen el caso). Los nombres de las rutas son los del sistema publicado.'));
CICLOS.forEach(([c, t]) => {
    add(H4(t));
    deCiclo(c).forEach(x => add(figura(`Diagrama de casos de uso ${x.id} ${x.nombre}`, DIAG(`cu_${x.id}`), NOTA_FUENTE, { alto: 230 }),
        ficha(x, ACTORES, { resumen: COMPLEMENTO[x.id][0], excepciones: COMPLEMENTO[x.id][2] })));
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
    add(H4(t));
    PROTOS[c].forEach(([f, tt]) => fs.existsSync(CAP(f)) && add(figura(tt, CAP(f), `Captura del sistema publicado en ${SITIO}.`, f.endsWith('movil') ? { ancho: 250, alto: 540 } : { alto: 315 })));
});
add(H2('2.5 Estructurar modelo de casos de uso'));
add(figura('Diagrama general: actores y paquetes', DIAG('cu_general'), NOTA_FUENTE));
CICLOS.forEach(([c, t]) => {
    add(H4(t));
    add(figura(`Diagrama de casos de uso del ${t.split(':')[0].toLowerCase()}`, DIAG(`cu_ciclo_${c}`), NOTA_FUENTE, { alto: 600 }));
});

// ===========================================================================
// CAPÍTULO 3. ANÁLISIS
// ===========================================================================
add(H1('Capítulo 3. Flujo de trabajo: análisis'));
add(H2('3.1 Análisis de la arquitectura'));
add(H3('3.1.1 Identificar paquetes'));
add(P('Los casos de uso se agrupan en paquetes según el área del taller que atienden. Cada paquete se construye en un ciclo; los paquetes del ciclo IV (cafetería, cobros y reportes) quedan planificados.'));
const DESC_PAQ = {
    P1: 'Gestiona el acceso al sistema y su configuración: inicio y cierre de sesión, recuperación de contraseña, usuarios, roles con su matriz de permisos, bitácora de auditoría y catálogos generales (categorías, unidades y métodos de pago). Garantiza que cada persona vea y haga solo lo que su rol permite.',
    P2: 'Administra la enseñanza: maestras, talleres, horarios semanales, generación del calendario de clases, reservas con control de cupo, registro de asistencia y alumnos con su portal. Reemplaza la hoja de Excel de horarios y el cuaderno de reservas.',
    P3: 'Controla los insumos de producción: materiales con stock mínimo, proveedores, compras con costo promedio y el kardex de entradas, consumos, mermas y ajustes. Evita quedarse sin arcilla o esmalte en plena producción.',
    P4: 'Reúne todo lo que genera ingresos: piezas de cerámica con foto, venta en caja con nota numerada, apertura y cierre de caja con arqueo, tienda en línea con carrito, pasarela de pago (QR, tarjeta o PayPal) y gestión de pedidos web.',
};
Object.entries(PAQUETES).forEach(([p, n]) => add(
    figura(`Paquete ${n}`, DIAG(`paq_${p}`), NOTA_FUENTE, { ancho: 300 }),
    new d.Paragraph({ keepNext: true, spacing: { before: 120 }, children: W.runs(`**${n.toUpperCase()}**`) }),
    P(DESC_PAQ[p]),
));
add(tabla('Resumen de paquetes de análisis', ['Paquete', 'Responsabilidad', 'Ciclo'], [
    ['P1 Seguridad y configuración', 'Acceso, usuarios, roles, permisos, bitácora, catálogos', 'I'],
    ['P2 Clases y reservas', 'Maestras, talleres, horarios, calendario, reservas, asistencia, alumnos', 'II'],
    ['P3 Inventario y compras', 'Materiales, proveedores, compras, kardex y alertas', 'III'],
    ['P4 Ventas, caja y tienda en línea', 'Piezas, clientes, ventas, caja, tienda, pedidos y pagos', 'III'],
    ['P5 Cafetería · P6 Cobros · P7 Reportes', 'Menú, comandas, mensualidades, reportes', 'IV (planificado)'],
], null, { anchos: [33, 52, 15] }));
add(H3('3.1.2 Relacionar paquetes y casos de uso'));
add(P('Cada paquete incluye los casos de uso que le pertenecen. Los diagramas siguientes muestran, para cada paquete, sus casos de uso unidos con la relación «include».'));
Object.entries(PAQUETES).forEach(([p, n]) => add(figura(`Paquete ${n} y sus casos de uso`, DIAG(`relpaq_${p}`), NOTA_FUENTE, { alto: 420 })));
add(H3('3.1.3 Vista de paquetes'));
add(P('La vista de cada paquete muestra, dentro del paquete, los actores que participan y los casos de uso que ejecutan, incluidas las relaciones «include» y «extend» entre casos de uso del mismo paquete.'));
Object.entries(PAQUETES).forEach(([p, n]) => add(figura(`Vista del paquete ${n}`, DIAG(`vista_${p}`), NOTA_FUENTE, { alto: 520 })));
add(H2('3.2 Analizar un caso de uso'));
add(P('Para cada caso de uso se elaboró un diagrama de comunicación con las clases de análisis: el actor, la interfaz «frm», el controlador «ctrl» y las entidades «entidad». Los mensajes están numerados en el orden en que ocurren; los que dependen de una condición llevan la guarda entre corchetes y los que se repiten llevan un asterisco. Por su ancho, los diagramas se presentan en páginas horizontales.'));
seccion(true);
CICLOS.forEach(([c, t]) => {
    add(H4(t));
    deCiclo(c).forEach(x => add(figura(`Diagrama de comunicación ${x.id} ${x.nombre}`, DIAG(`com_${x.id}`), NOTA_FUENTE, { alto: 510 })));
});
seccion();
add(H2('3.3 Análisis de clase'));
add(P('Para cada caso de uso se elaboró un diagrama de clases de análisis con tres tipos de clase: la interfaz «form» (lo que ve el usuario, con sus campos y acciones), el controlador «control» (coordina el caso de uso) y las entidades «entidad» (datos que persisten, con los atributos reales de su tabla). Las clases «procedimiento» son funciones de PostgreSQL que ejecutan una operación completa en una transacción. Los métodos son los mensajes del diagrama de comunicación del mismo caso de uso.'));
seccion(true);
CICLOS.forEach(([c, t]) => {
    add(H4(t));
    deCiclo(c).forEach(x => add(figura(`Clases de análisis ${x.id} ${x.nombre}`, DIAG(`clases_${x.id}`), NOTA_FUENTE, { alto: 500 })));
});
seccion();
add(H2('3.4 Análisis de paquetes'));
add(P('Todos los paquetes dependen de P1, porque cada acción verifica la sesión y el permiso del usuario. P4 depende de P3 porque las piezas comparten categorías con el inventario. No hay dependencias en ciclo, por lo que cada paquete puede construirse y probarse en su propio ciclo.'));
add(figura('Análisis de paquetes: dependencias', DIAG('paquetes'), NOTA_FUENTE, { ancho: 560 }));
add(tabla('Contenido de cada paquete', ['Paquete', 'Interfaces', 'Controladores', 'Entidades'], Object.keys(PAQUETES).map(p => {
    const set = t => [...new Set(CASOS.filter(x => x.paquete === p).flatMap(x => x.participantes.filter(q => t.includes(q.tipo)).map(q => q.nombre)))].join(', ');
    return [PAQUETES[p], set(['frm']), set(['ctrl']), set(['ent', 'bd'])];
}), null, { anchos: [19, 27, 22, 32], tam: 18 }));

// ===========================================================================
// CAPÍTULO 4. DISEÑO
// ===========================================================================
add(H1('Capítulo 4. Flujo de trabajo: diseño'));
add(H2('4.1 Diseño de arquitectura'));
add(H3('4.1.1 Diseño de datos lógico (diagrama de despliegue)'));
add(P('El navegador del usuario se comunica por HTTPS con Vercel, donde corre la aplicación PHP. La aplicación se conecta a PostgreSQL en Supabase a través del pooler Supavisor en modo transacción (puerto 6543) con un usuario de mínimo privilegio. El correo SMTP y el ingreso con Google son servicios externos opcionales.'));
add(figura('Diagrama de despliegue', DIAG('despliegue'), NOTA_FUENTE));
add(H3('4.1.2 Diseño de datos físico (diagrama organizado en capas)'));
add(P('La aplicación sigue el patrón MVC con cinco capas. Cada capa solo usa la de abajo: las vistas no consultan la base de datos y los modelos no generan HTML. Las reglas críticas se repiten en la capa de datos (triggers) como última defensa.'));
add(figura('Arquitectura en capas', DIAG('capas'), NOTA_FUENTE, { ancho: 560 }));

// ===========================================================================
// CAPÍTULO 5. DISEÑO DE DATOS
// ===========================================================================
add(H1('Capítulo 5. Diseño de datos'));
add(H2('5.1 Diseño de datos lógico'));
add(H3('Diagrama de clase'));
add(P('Las clases persistentes se derivan de las entidades de análisis. Se muestran por paquete; las llaves foráneas se representan como asociaciones y las composiciones (rombo negro) indican que la parte no existe sin el todo, como el detalle de una venta.'));
Object.entries(PAQUETES).forEach(([p, n]) => add(figura(`Clases persistentes de ${n}`, DIAG(`per_${p}`), NOTA_FUENTE, { alto: 600 })));
add(H3('Mapeo'));
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
add(P(`El diccionario de datos describe cada una de las ${Object.keys(ESQUEMA).length} tablas de la base publicada: atributo, tipo de dato, amplitud, llave, si admite nulos y una descripción. Se generó desde el catálogo de PostgreSQL, por lo que coincide con la base real.`));
const DESC_COL = {
    id: 'Identificador único', nombre: 'Nombre', apellido: 'Apellido', correo: 'Correo electrónico', telefono: 'Teléfono de contacto',
    estado: 'Estado del registro', descripcion: 'Descripción', creado_en: 'Fecha y hora de creación', actualizado_en: 'Fecha y hora de la última modificación',
    fecha: 'Fecha del registro', total: 'Importe total (Bs)', precio: 'Precio (Bs)', cantidad: 'Cantidad', stock: 'Existencia actual',
    stock_minimo: 'Existencia mínima antes de alertar', costo: 'Costo unitario (Bs)', costo_promedio: 'Costo promedio ponderado (Bs)', numero: 'Número correlativo del documento',
    observacion: 'Observación', motivo: 'Motivo del cambio o anulación', codigo: 'Código', clave_hash: 'Contraseña cifrada con bcrypt', ultimo_acceso: 'Último ingreso al sistema',
    ci: 'Cédula de identidad', nit: 'NIT', direccion: 'Dirección', subtotal: 'Subtotal de la línea (Bs)', precio_unitario: 'Precio unitario aplicado (Bs)',
    hora_inicio: 'Hora de inicio', hora_fin: 'Hora de fin', dia_semana: 'Día de la semana (1 = lunes)', cupo: 'Cupo máximo de alumnos', tipo: 'Tipo o clase del registro',
    accion: 'Acción realizada', modulo: 'Módulo del sistema', ip: 'Dirección IP de origen', detalle: 'Detalle en formato JSON', valor: 'Valor configurado', clave: 'Nombre del parámetro',
};
const descCol = n => DESC_COL[n] || (n.endsWith('_id') ? `Referencia a ${n.slice(0, -3).replace(/_/g, ' ')}` : n.charAt(0).toUpperCase() + n.slice(1).replace(/_/g, ' '));
Object.keys(ESQUEMA).forEach(t => {
    const pkc = ((restr(t, 'p')[0] || {}).columnas || '').split(',');
    const fkc = new Set(restr(t, 'f').flatMap(k => k.columnas.split(',')));
    add(tabla(`Tabla ${t}`, ['Atributo', 'Tipo de dato', 'Amplitud', 'Llave', 'Nulo', 'Descripción'], ESQUEMA[t].columnas.map(col => {
        const m = col.tipo.match(/^(.*?)\s*\(([^)]*)\)$/);
        const base = (m ? m[1] : col.tipo).replace('character varying', 'varchar').replace('timestamp with time zone', 'timestamptz').replace('timestamp without time zone', 'timestamp').replace('time without time zone', 'time');
        const llave = [pkc.includes(col.nombre) ? 'PK' : '', fkc.has(col.nombre) ? 'FK' : ''].filter(Boolean).join(' / ');
        return [col.nombre, base, m ? m[2] : '', llave, col.obligatorio ? 'No' : 'Sí', descCol(col.nombre)];
    }), null, { anchos: [22, 16, 11, 10, 8, 33], tam: 17, mono: [0] }));
});
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
const PAS = [
    ['PA1', 'fn_registrar_venta', 'Registra la venta, su detalle y descuenta el stock'], ['PA2', 'fn_anular_venta', 'Anula con motivo y devuelve el stock'],
    ['PA3', 'fn_registrar_compra', 'Registra la compra y sube stock y costo promedio'], ['PA4', 'fn_anular_compra', 'Anula la compra y revierte el stock'],
    ['PA5', 'fn_reservar_clase', 'Reserva un lugar validando cupo y fecha'], ['PA6', 'fn_generar_sesiones', 'Crea las clases del calendario sin duplicar'],
    ['PA7', 'sp_cerrar_caja', 'Cierra la caja y calcula el arqueo'], ['PA8', 'fn_crear_pedido', 'Crea el pedido en línea y reserva el stock 30 minutos'],
    ['PA9', 'fn_registrar_pago', 'Registra el pago y marca el pedido como pagado'], ['PA10', 'fn_verificar_pago_qr', 'El taller confirma o rechaza un pago por QR'],
    ['PA11', 'fn_cancelar_pedido', 'Cancela y devuelve el stock'], ['PA12', 'fn_vencer_pedidos', 'Cancela los pedidos no pagados a tiempo'],
];
add(tabla('Procedimientos almacenados', ['Código', 'Función', 'Qué hace'], PAS, null, { anchos: [10, 30, 60], mono: [1] }));
add(P('El código de cada procedimiento se copia tal cual de los archivos del repositorio (Bakend/database). Debajo se muestra la prueba que se ejecutó en la base publicada y su resultado real.'));
/** Código de una función o procedimiento, copiado del script SQL del repositorio. */
const definicion = nombre => {
    for (const f of ['06_procedimientos_triggers.sql', '07_tienda_pasarela.sql']) {
        const s = SQL(f);
        const i = s.search(new RegExp(`create or replace (function|procedure) mitsu\\.${nombre}\\(`));
        if (i >= 0) return [f, s.slice(i, s.indexOf('$$;', i) + 3)];
    }
    throw new Error('No se encontró ' + nombre + ' en los scripts SQL');
};
PAS.forEach(([id, nombre]) => {
    const [archivo, sql] = definicion(nombre);
    add(H4(`${id} ${nombre}`), P(`Código (Bakend/database/${archivo}):`, { sinSangria: true }), codigo(sql));
    const pa = BD.procedimientos.find(x => x.id === id);
    if (pa) {
        add(P(`Prueba: ${pa.descripcion}.`, { sinSangria: true }), codigo(pa.sql), pa.ok ? resultado(pa.columnas, pa.filas) : P('Error: ' + pa.error));
    } else {
        add(P(id === 'PA10' ? 'Se prueba en el flujo completo de la tienda (Anexo C): el personal confirma el pago por QR de un pedido en verificación.'
                            : 'Se prueba en el flujo completo de la tienda (Anexo C): cancela los pedidos que no se pagaron en 30 minutos y devuelve el stock.', { sinSangria: true }));
    }
});
add(H3('Triggers (disparadores)'));
add(P('Los triggers garantizan las reglas del negocio dentro de la base de datos, aunque alguien intente saltarse la aplicación. Cada prueba intenta violar una regla; el resultado esperado es que la base la rechace con un mensaje claro.'));
add(tabla('Prueba de triggers y restricciones', ['Regla', 'Intento', 'Respuesta real de la base'],
    BD.triggers.map(t => [t.regla, t.descripcion, t.ok ? 'Aceptado: ' + JSON.stringify(t.filas[0]) : t.error]),
    'Ejecutado contra la base publicada con el usuario de la aplicación; cada prueba se revirtió. En RN08 la venta se acepta pero con el precio real de la pieza (Bs 95), no con el enviado.', { anchos: [26, 30, 44], tam: 18 }));
const s02 = SQL('02_reglas_negocio.sql');
const it = s02.indexOf('create or replace function mitsu.tg_reserva_validar');
if (it < 0) throw new Error('No se encontró tg_reserva_validar en 02_reglas_negocio.sql');
add(P('Ejemplo: trigger que valida una reserva (bloquea la clase y revisa cupo y fecha), copiado de Bakend/database/02_reglas_negocio.sql:', { sinSangria: true }));
add(codigo(s02.slice(it, s02.indexOf('$$;', it) + 3)));
add(H2('5.3 Diseñar casos de uso (diagrama de secuencia)'));
add(P('Los diagramas de secuencia muestran, en orden temporal, los mensajes entre el actor, la interfaz, el controlador y las entidades, con los fragmentos alt (alternativa), opt (opcional) y loop (repetición). Los nombres de controladores y métodos son los del código fuente.'));
seccion(true);
CICLOS.forEach(([c, t]) => {
    add(H4(t));
    deCiclo(c).forEach(x => add(figura(`Diagrama de secuencia ${x.id} ${x.nombre}`, DIAG(`sec_${x.id}`), NOTA_FUENTE, { alto: 510 })));
});
seccion();

// ===========================================================================
// CAPÍTULO 6. IMPLEMENTACIÓN
// ===========================================================================
add(H1('Capítulo 6. Flujo de trabajo: implementación'));
add(H2('6.1 Elección de la plataforma de desarrollo del software'));
[
    ['Lenguaje de programación', '**PHP 8.3** sin framework, con un MVC propio. Es conocido por el equipo, gratuito y soportado en Vercel; al no usar framework se ve y se explica cada capa. La interfaz usa HTML5, CSS3 y JavaScript propio, adaptable al celular y con modo claro y oscuro.'],
    ['Base de datos', '**PostgreSQL 17** en Supabase. Permite triggers, funciones PL/pgSQL, restricciones EXCLUDE para que no se crucen los horarios y seguridad por filas.'],
    ['Sistemas operativos', 'En producción, **Linux** en la nube (Vercel y Supabase), por lo que no hay servidores que administrar. En desarrollo, **Windows 11** con el servidor integrado de PHP.'],
    ['Otros', 'Git y GitHub para el control de versiones, PDO con pdo_pgsql para el acceso seguro a datos, Puppeteer para las pruebas y capturas, y Visual Studio Code como editor.'],
].forEach(([t, x]) => add(H3(t), P(x)));
add(H2('6.2 Implementación de la arquitectura del sistema'));
add(H3('Diagrama de componentes principal del sistema'));
add(figura('Diagrama de componentes principal', DIAG('componentes'), NOTA_FUENTE));
add(H4('Estructura de carpetas'));
add(P('Árbol generado con git ls-files: muestra las carpetas del repositorio hasta tres niveles y los archivos de la raíz y de api/.', { sinSangria: true }));
add(codigo(arbolGit()));
add(H2('6.3 Implementación de la arquitectura del subsistema'));
add(H3('Diagrama de componentes de cada paquete'));
Object.entries(PAQUETES).forEach(([p, n]) => add(figura(`Componentes de ${n}`, DIAG(`componentes_${p}`), NOTA_FUENTE, { alto: 600 })));
add(H4('Seguridad implementada'));
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
    ['Docs/pruebas/probar_tienda.php', '34 verificaciones de la tienda: carrito sin cuenta, ingreso que vuelve al pago, Luhn, tarjeta vencida, rechazada y aprobada, cobro doble, PayPal, QR simulado (pago realizado y no realizado), QR verificado por el taller, entrega y cancelación con devolución de stock, seguridad del cliente y recuperación de contraseña con el código en pantalla.', 'Todo correcto'],
    ['Docs/informe/resultados_bd.php', '30 consultas, 10 procedimientos y 11 pruebas de reglas contra la base publicada.', '0 errores'],
], 'Los scripts se ejecutaron en local y contra el sistema publicado durante el desarrollo de los ciclos I a III.', { anchos: [30, 52, 18], mono: [0] }));
add(H2('Anexo D. Manual de uso'));
[
    ['Visitante y cliente', ['Entre a la página principal: verá las piezas y las clases.', 'Abra una pieza y presione “Agregar al carrito”.', 'En el carrito presione “Pagar”. Si no tiene cuenta, créela con su correo; el sistema lo devuelve al carrito.', 'Elija retiro en el taller o envío, escriba su teléfono y confirme: las piezas quedan reservadas 30 minutos.', 'Pague con el QR del taller (en modo demostración, “Pago realizado” simula la respuesta del banco), con tarjeta o con PayPal. Verá su comprobante en “Mis pedidos”.']],
    ['Alumno', ['Cree su cuenta en “Crea tu cuenta de alumno” (si es menor, con datos del tutor).', 'En “Reservar clase” elija una clase con lugares libres y presione “Reservar mi lugar”.', 'Puede cancelar una reserva hasta 12 horas antes desde su inicio.']],
    ['Maestra', ['Ingrese con su correo: verá sus clases de la semana.', 'Abra la clase del día, marque “Presente” o “Falta” en cada alumno y guarde.']],
    ['Administrativo', ['Abra la caja al iniciar el día con el monto inicial.', 'En “Vender” toque las piezas, elija cliente y método de pago, escriba el monto recibido y presione “Cobrar y emitir nota”.', 'Registre compras en “Compras → Nueva compra”; el stock sube solo.', 'Registre consumo o merma desde el kardex de cada material.', 'En “Pedidos web” confirme los pagos por QR y marque los pedidos entregados.', 'Al cerrar, cuente el efectivo y cierre la caja: el sistema muestra la diferencia.']],
    ['Administrador', ['Cree usuarios y asígneles un rol en “Usuarios”.', 'Ajuste qué puede hacer cada rol en “Roles y permisos”.', 'Registre maestras, talleres y horarios; genere el calendario.', 'Revise la bitácora y anule ventas con motivo cuando corresponda.']],
].forEach(([rol, pasos]) => add(H4(rol), L(pasos, true)));
add(H2('Anexo E. Script completo de la base de datos'));
add(P('Se incluyen los siete archivos de la carpeta Bakend/database, copiados tal cual están en el repositorio y en el orden de instalación indicado en el README.'));
fs.readdirSync(path.join(RAIZ, 'Bakend', 'database')).filter(f => f.endsWith('.sql')).sort().forEach(f => add(H4(f), codigo(SQL(f), 15)));
add(salto());
add(H2('Anexo F. Enlaces del proyecto'));
add(P('Los siguientes códigos QR permiten abrir el sistema publicado y el repositorio desde un celular. Basta con escanearlos con la cámara.'));
add(new d.Table({
    width: { size: 100, type: d.WidthType.PERCENTAGE },
    rows: [new d.TableRow({
        children: [
            ['qr_sistema.png', 'Sistema en línea', SITIO, 'Abre la tienda pública del taller. Desde «Ingresar» se accede al panel con las cuentas del Anexo B.'],
            ['qr_repositorio.png', 'Repositorio en GitHub', REPO, 'Código fuente, scripts SQL, diagramas, pruebas y este informe, con la estructura de carpetas del punto 6.2.'],
        ].map(([img, t, url, texto]) => new d.TableCell({
            width: { size: 50, type: d.WidthType.PERCENTAGE },
            margins: { top: 200, bottom: 200, left: 200, right: 200 },
            borders: { top: W.SIN, bottom: W.SIN, left: W.SIN, right: W.SIN },
            children: [
                new d.Paragraph({ alignment: d.AlignmentType.CENTER, children: [W.imagen(REC(img), 200, 200)] }),
                new d.Paragraph({ alignment: d.AlignmentType.CENTER, spacing: { before: 160, after: 60 }, children: [new d.TextRun({ text: t, bold: true, font: W.FUENTE })] }),
                new d.Paragraph({ alignment: d.AlignmentType.CENTER, spacing: { after: 120 }, children: [new d.ExternalHyperlink({ link: url, children: [new d.TextRun({ text: url, style: 'Hyperlink', size: 20, font: W.FUENTE })] })] }),
                new d.Paragraph({ alignment: d.AlignmentType.CENTER, children: [new d.TextRun({ text: texto, size: 20, font: W.FUENTE })] }),
            ],
        })),
    })],
}));
add(P('Todas las contraseñas de las cuentas de prueba son inventadas para la demostración; las claves reales de la base de datos están en variables de entorno y no aparecen en el repositorio.', { sinSangria: true }));

// ===========================================================================
// DOCUMENTO
// ===========================================================================
const estiloTitulo = (id, nombre, op) => ({
    id, name: nombre, basedOn: 'Normal', next: 'Normal', quickFormat: true,
    run: { font: W.FUENTE, size: 24, bold: true, italics: !!op.cursiva, color: '000000' },
    paragraph: { alignment: op.centro ? d.AlignmentType.CENTER : d.AlignmentType.LEFT, spacing: { before: op.antes ?? 240, after: 120, line: 360 }, keepNext: true, keepLines: true, outlineLevel: op.nivel },
});
const doc = new d.Document({
    creator: GRUPO, title: 'Sistema de Información para la Gestión Integral del Taller de Cerámica Mitsü', description: 'Informe final ciclos I a III',
    features: { updateFields: true },
    styles: {
        default: { document: { run: { font: W.FUENTE, size: 24 } } },
        paragraphStyles: [
            estiloTitulo('Heading1', 'Heading 1', { centro: true, nivel: 0, antes: 0 }),
            estiloTitulo('Heading2', 'Heading 2', { nivel: 1 }),
            estiloTitulo('Heading3', 'Heading 3', { cursiva: true, nivel: 2 }),
            estiloTitulo('Heading4', 'Heading 4', { cursiva: true, nivel: 3, antes: 160 }),
            estiloTitulo('Subtitulo', 'Subtítulo', { cursiva: true, antes: 160 }),
        ],
    },
    sections: secciones,
});
const salida = path.join(AQUI, 'Informe_Taller_Mitsu_Ciclos_I-III.docx');
d.Packer.toBuffer(doc).then(b => {
    fs.writeFileSync(salida, b);
    console.log('Informe generado:', path.basename(salida), Math.round(b.length / 1024), 'KB');
});
