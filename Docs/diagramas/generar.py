"""
Genera todos los diagramas del informe en Docs/diagramas/svg y la lista de casos
de uso en Docs/informe/datos/casos.json.

    python Docs/diagramas/generar.py
    node Docs/diagramas/render.js        (convierte a PNG)
"""

import json
import os
import sys

sys.path.insert(0, os.path.dirname(__file__))
import uml
from uml import Lienzo, clase, asociacion, paquete, nodo, componente, ancho, RELLENO, RELLENO_2, GRIS, TINTA, ACENTO
from casos import CASOS, ACTORES, PAQUETES

AQUI = os.path.dirname(os.path.abspath(__file__))
SVG = os.path.join(AQUI, 'svg')
DATOS = os.path.join(AQUI, '..', 'informe', 'datos')
os.makedirs(SVG, exist_ok=True)
ESQUEMA = json.load(open(os.path.join(DATOS, 'esquema.json'), encoding='utf8'))['tablas']


def ruta(nombre):
    return os.path.join(SVG, nombre + '.svg')


CICLOS = {'I': 'Ciclo I · Seguridad y configuración', 'II': 'Ciclo II · Clases y reservas', 'III': 'Ciclo III · Inventario, ventas y tienda en línea'}

# ---------------------------------------------------------------------------
# 1. Secuencia y comunicación por caso de uso
# ---------------------------------------------------------------------------
for cu in CASOS:
    titulo = f"{cu['id']} {cu['nombre']}"
    cu['mensajes'] = uml.secuencia(titulo, cu['part'], cu['pasos'], ruta(f"sec_{cu['id']}"))
    uml.comunicacion(titulo, cu['part'], cu['pasos'], ruta(f"com_{cu['id']}"))

# ---------------------------------------------------------------------------
# 2. Casos de uso
# ---------------------------------------------------------------------------
LADO = {'adm': 'izq', 'admv': 'izq', 'mae': 'der', 'alu': 'der', 'cli': 'der', 'vis': 'der', 'usr': 'izq'}


def actores_de(casos):
    claves = []
    for cu in casos:
        for a in cu['actores']:
            if a not in claves:
                claves.append(a)
    return claves


for ciclo, titulo in CICLOS.items():
    lista = [cu for cu in CASOS if cu['ciclo'] == ciclo]
    acts = actores_de(lista)
    rel = [(a, cu['id']) for cu in lista for a in cu['actores']]
    extras = []
    if ciclo == 'II':
        extras = [('CU10', 'CU09', '«include»')]
    if ciclo == 'III':
        extras = [('CU20', 'CU21', '«include»'), ('CU18', 'CU19', '«include»'), ('CU16', 'CU13', '«extend»')]
    uml.casos_de_uso(titulo, [(a, ACTORES[a], LADO[a]) for a in acts],
                     [(cu['id'], f"{cu['id']} {cu['nombre']}") for cu in lista], rel, ruta(f'cu_ciclo_{ciclo}'), extras=extras, ancho_caso=270)

# diagrama general: actores con los paquetes
c = Lienzo()
paq_pos = {}
for k, (p, nombre) in enumerate(PAQUETES.items()):
    x, y = 0, k * 130
    paq_pos[p] = paquete(c, x, y, 360, 100, nombre)
    casos_p = [cu['id'] for cu in CASOS if cu['paquete'] == p]
    c.texto(x + 180, y + 66, ' · '.join(casos_p[:5]), 11, 'middle', color=GRIS)
    if len(casos_p) > 5:
        c.texto(x + 180, y + 82, ' · '.join(casos_p[5:]), 11, 'middle', color=GRIS)
act_pos = {'adm': (-230, 90), 'admv': (-230, 330), 'mae': (590, 150), 'alu': (590, 290), 'cli': (590, 430), 'vis': (590, 20)}
usos = {'adm': ['P1', 'P2', 'P3', 'P4'], 'admv': ['P2', 'P3', 'P4'], 'mae': ['P2'], 'alu': ['P2', 'P4'], 'cli': ['P4'], 'vis': ['P4']}
for a, ps in usos.items():
    for p in ps:
        x, y, w, h = paq_pos[p]
        ax, ay = act_pos[a]
        c.linea([(ax + (16 if ax < 0 else -16), ay), (x if ax < 0 else x + w, y + h / 2)])
for a, (x, y) in act_pos.items():
    fondo = uml.icono(c, 'actor', x, y - 10)
    c.texto(x, fondo + 14, ACTORES[a], 12, 'middle', negrita=True)
c.guardar(ruta('cu_general'))

# generalización de actores
c = Lienzo()
cajas = {}
pos = {'usr': (300, 0), 'adm': (0, 170), 'admv': (150, 170), 'mae': (300, 170), 'alu': (450, 170), 'cli': (600, 170), 'vis': (600, 0)}
for a, (x, y) in pos.items():
    fondo = uml.icono(c, 'actor', x, y)
    c.texto(x, fondo + 14, ACTORES[a], 12, 'middle', negrita=True)
    cajas[a] = (x - 30, y - 22, 60, 66)
for a in ('adm', 'admv', 'mae', 'alu', 'cli'):
    x = pos[a][0]
    c.linea([(x, 140), (x, 110), (300, 110), (300, 72)], fin='triangulo' if a == 'mae' else None)
c.linea([(600, 140), (600, 70)], fin='triangulo')
c.texto(620, 108, 'el cliente es un visitante que ingresó', 10.5, color=GRIS)
c.texto(300 + 10, 96, 'todos los usuarios inician sesión (CU01, CU02)', 10.5, color=GRIS)
c.guardar(ruta('actores'))

# ---------------------------------------------------------------------------
# 3. Paquetes
# ---------------------------------------------------------------------------
c = Lienzo()
P = {}
P['P1'] = paquete(c, 260, 0, 260, 90, PAQUETES['P1'])
P['P2'] = paquete(c, 0, 190, 260, 90, PAQUETES['P2'])
P['P3'] = paquete(c, 330, 190, 260, 90, PAQUETES['P3'])
P['P4'] = paquete(c, 660, 190, 280, 90, PAQUETES['P4'])
for p in ('P2', 'P3', 'P4'):
    asociacion(c, P[p], P['P1'], tipo='dep', etiqueta='«usa»')
asociacion(c, P['P4'], P['P3'], tipo='dep', etiqueta='«usa»')
for p, t in (('P1', 'Ciclo I'), ('P2', 'Ciclo II'), ('P3', 'Ciclo III'), ('P4', 'Ciclo III')):
    x, y, w, h = P[p]
    c.texto(x + w / 2, y + h / 2 + 5, t, 12, 'middle', color=GRIS)
c.guardar(ruta('paquetes'))

# paquetes con sus casos de uso
c = Lienzo()
x = 0
for p, nombre in PAQUETES.items():
    lista = [cu for cu in CASOS if cu['paquete'] == p]
    alto = 50 + len(lista) * 58
    paquete(c, x, 0, 270, alto, nombre)
    for k, cu in enumerate(lista):
        cy = 60 + k * 58
        c.elipse(x + 135, cy, 120, 22)
        partes = uml._partir(f"{cu['id']} {cu['nombre']}", 210, 11)
        for j, t in enumerate(partes):
            c.texto(x + 135, cy + 4 - 6.5 * (len(partes) - 1) + 13 * j, t, 11, 'middle')
    x += 300
c.guardar(ruta('paquetes_casos'))

# ---------------------------------------------------------------------------
# 4. Clases de análisis por ciclo (interfaz → control → entidad)
# ---------------------------------------------------------------------------
for ciclo, titulo in CICLOS.items():
    lista = [cu for cu in CASOS if cu['ciclo'] == ciclo]
    cols = {'actor': [], 'frm': [], 'ctrl': [], 'ent': []}
    aristas = set()
    for cu in lista:
        tipos = {k: (t if t != 'bd' else 'ent', n) for k, t, n in cu['part']}
        for k, (t, n) in tipos.items():
            if n not in cols[t]:
                cols[t].append(n)
        actor = tipos['a'][1]
        for a in cu['actores']:
            if ACTORES[a] not in cols['actor']:
                cols['actor'].append(ACTORES[a])
            aristas.add((ACTORES[a], tipos['f'][1]))
        aristas.add((tipos['f'][1], tipos['c'][1]))
        for k, (t, n) in tipos.items():
            if t == 'ent':
                aristas.add((tipos['c'][1], n))
    for actor in list(cols['actor']):
        if not any(a == actor for a, _ in aristas):
            cols['actor'].remove(actor)
    # ordenar por baricentro respecto a la columna anterior
    orden = ['actor', 'frm', 'ctrl', 'ent']
    for i in range(1, 4):
        prev = cols[orden[i - 1]]
        def bc(n):
            vs = [prev.index(a) for a, b in aristas if b == n and a in prev]
            return sum(vs) / len(vs) if vs else 0
        cols[orden[i]].sort(key=bc)
    c = Lienzo()
    pos = {}
    xs = {'actor': 0, 'frm': 220, 'ctrl': 480, 'ent': 760}
    alto = max(len(v) for v in cols.values()) * 84
    for t, nombres in cols.items():
        paso = alto / max(1, len(nombres))
        for k, n in enumerate(nombres):
            pos[(t, n)] = (xs[t], paso * (k + 0.5))
    def buscar(n, tipos):
        for t in tipos:
            if (t, n) in pos:
                return pos[(t, n)]
    for a, b in sorted(aristas):
        pa = buscar(a, ['actor', 'frm', 'ctrl'])
        pb = buscar(b, ['frm', 'ctrl', 'ent'] if a in cols['actor'] else ['ctrl', 'ent'])
        c.linea([(pa[0] + 18, pa[1]), (pb[0] - 18 - (10 if b in cols['frm'] else 0), pb[1])])
    for (t, n), (x, y) in pos.items():
        fondo = uml.icono(c, t, x, y - (8 if t == 'actor' else 0))
        if t == 'actor':
            c.texto(x, fondo + 13, n, 11.5, 'middle', negrita=True)
        else:
            c.texto(x, fondo + 14, n, 11.5, 'middle', negrita=True, fondo='white')
    c.guardar(ruta(f'analisis_ciclo_{ciclo}'))

# ---------------------------------------------------------------------------
# 5. Modelo de dominio (conceptual)
# ---------------------------------------------------------------------------
def rejilla(c, filas, crear, gapx=50, gapy=60):
    cajas, y = {}, 0
    anchos = []
    tmp = Lienzo()
    medidas = {}
    for fila in filas:
        for n in fila:
            medidas[n] = crear(tmp, 0, 0, n)
    total = max(sum(medidas[n][2] for n in fila) + gapx * (len(fila) - 1) for fila in filas)
    for fila in filas:
        w = sum(medidas[n][2] for n in fila) + gapx * (len(fila) - 1)
        x = (total - w) / 2
        h = max(medidas[n][3] for n in fila)
        for n in fila:
            cajas[n] = crear(c, x, y, n)
            x += medidas[n][2] + gapx
        y += h + gapy
    return cajas


DOMINIO = {
    'Usuario': ['nombre', 'correo', 'estado'], 'Rol': ['nombre'], 'Permiso': ['código'],
    'Maestra': ['especialidad'], 'Alumno': ['ci', 'fecha de nacimiento', 'tutor'], 'Taller': ['nombre', 'nivel', 'precio'],
    'Horario': ['día', 'hora', 'cupo'], 'Clase': ['fecha', 'estado'], 'Reserva': ['estado (asistió/faltó)'],
    'Proveedor': ['nombre', 'NIT'], 'Compra': ['fecha', 'total'], 'Material': ['stock', 'stock mínimo', 'costo'],
    'Pieza': ['código', 'precio', 'stock', 'foto'], 'Venta': ['número', 'total', 'estado'], 'Caja': ['monto inicial', 'diferencia'],
    'Cliente': ['nombre', 'CI/NIT'], 'Pedido': ['número', 'entrega', 'estado'], 'Pago': ['método', 'estado'],
}
c = Lienzo()
B = rejilla(c, [['Permiso', 'Rol', 'Usuario', 'Caja', 'Cliente'],
                ['Taller', 'Maestra', 'Alumno', 'Venta', 'Pedido', 'Pago'],
                ['Horario', 'Clase', 'Reserva', 'Pieza'],
                ['Proveedor', 'Compra', 'Material']],
            lambda cv, x, y, n: clase(cv, x, y, n, DOMINIO[n], minimo=120), gapx=95, gapy=90)
for a, b, m1, m2, et in [('Rol', 'Permiso', '*', '*', 'tiene'), ('Usuario', 'Rol', '*', '1', 'pertenece'),
                         ('Maestra', 'Usuario', '0..1', '1', 'es'), ('Alumno', 'Usuario', '0..1', '1', 'es'),
                         ('Horario', 'Taller', '*', '1', ''), ('Horario', 'Maestra', '*', '1', 'dicta'), ('Clase', 'Horario', '*', '1', 'genera'),
                         ('Reserva', 'Clase', '*', '1', ''), ('Reserva', 'Alumno', '*', '1', 'reserva'),
                         ('Compra', 'Proveedor', '*', '1', ''), ('Compra', 'Material', '*', '1..*', 'incluye'),
                         ('Venta', 'Pieza', '*', '1..*', 'incluye'), ('Venta', 'Caja', '*', '1', ''), ('Venta', 'Cliente', '*', '0..1', ''),
                         ('Pedido', 'Cliente', '*', '1', 'hace'), ('Pago', 'Pedido', '*', '1', ''), ('Pedido', 'Pieza', '*', '1..*', 'incluye'),
                         ('Caja', 'Usuario', '*', '1', 'abre')]:
    asociacion(c, B[a], B[b], m1, m2, et)
c.guardar(ruta('dominio'))

# ---------------------------------------------------------------------------
# 6. Clases persistentes y diagrama relacional (desde el esquema real)
# ---------------------------------------------------------------------------
def nombre_clase(t):
    return ''.join(p.capitalize() for p in t.split('_'))


def fks(t):
    return [(k['columnas'], k['referencia']) for k in ESQUEMA[t].get('restricciones', []) if k['tipo'] == 'f']


def pk(t):
    for k in ESQUEMA[t].get('restricciones', []):
        if k['tipo'] == 'p':
            return k['columnas'].split(',')
    return []


def tipo_corto(t):
    return (t.replace('character varying', 'varchar').replace('timestamp with time zone', 'timestamptz')
             .replace('timestamp without time zone', 'timestamp').replace('time without time zone', 'time'))


def caja_persistente(cv, x, y, t):
    fk_cols = {c for cols, _ in fks(t) for c in cols.split(',')}
    attrs = [f"{col['nombre']}: {tipo_corto(col['tipo'])}" for col in ESQUEMA[t]['columnas']
             if col['nombre'] not in fk_cols and col['nombre'] != 'id']
    return clase(cv, x, y, nombre_clase(t), attrs[:9] + (['…'] if len(attrs) > 9 else []), minimo=150, tam=10.5)


def caja_relacional(cv, x, y, t):
    p = pk(t)
    fk_cols = {c for cols, _ in fks(t) for c in cols.split(',')}
    attrs = []
    for col in ESQUEMA[t]['columnas']:
        marca = 'PK ' if col['nombre'] in p else 'FK ' if col['nombre'] in fk_cols else '     '
        attrs.append(f"{marca}{col['nombre']}: {tipo_corto(col['tipo'])}")
    return clase(cv, x, y, t, attrs, minimo=170, tam=10.5, fill='white')


GRUPOS = {
    'P1': [['rol', 'rolpermiso', 'permiso'], ['bitacora', 'usuario', 'recuperacion'], ['sesion', 'configuracion', 'categoria', 'unidad', 'metodo_pago']],
    'P2': [['taller', 'horario', 'maestra'], ['sesion_clase', 'usuario'], ['reserva', 'alumno']],
    'P3': [['proveedor', 'compra', 'usuario'], ['detalle_compra', 'movimiento_material'], ['categoria', 'material', 'unidad']],
    'P4': [['caja', 'venta', 'metodo_pago', 'cliente'], ['detalle_venta', 'movimiento_pieza', 'pedido', 'pago'],
           ['historial_precio', 'pieza', 'detalle_pedido'], ['pieza_imagen']],
}
for p, filas in GRUPOS.items():
    for tipo, crear in (('rel', caja_relacional), ('per', caja_persistente)):
        c = Lienzo()
        B = rejilla(c, filas, crear, gapx=60, gapy=80)
        presentes = set(B)
        for t in presentes:
            for cols, ref in fks(t):
                if ref in presentes and ref != t:
                    if tipo == 'rel':
                        asociacion(c, B[t], B[ref], 'N', '1')
                    else:
                        comp = t.startswith('detalle_') or t in ('rolpermiso', 'pieza_imagen')
                        asociacion(c, B[ref], B[t], '1', '*', tipo='comp' if comp else 'asoc')
        c.guardar(ruta(f'{tipo}_{p}'))

# ---------------------------------------------------------------------------
# 7. Despliegue, capas y componentes
# ---------------------------------------------------------------------------
c = Lienzo()
n1 = nodo(c, 0, 40, 230, 150, 'PC o celular')
c.texto(115, 110, 'Navegador web', 11.5, 'middle')
c.texto(115, 128, 'HTML · CSS · JavaScript', 10.5, 'middle', color=GRIS)
n2 = nodo(c, 340, 0, 300, 230, 'Vercel (región gru1)', '«servidor»')
componente(c, 365, 60, 'api/index.php', 'PHP 8.3 · vercel-php', w=250)
componente(c, 365, 130, 'Assets estáticos', 'public/ · CSS · JS · imágenes', w=250)
n3 = nodo(c, 760, 0, 300, 230, 'Supabase (sa-east-1)', '«servidor»')
componente(c, 785, 60, 'Supavisor (pooler)', 'puerto 6543 · modo transacción', w=250)
componente(c, 785, 130, 'PostgreSQL 17', 'esquema mitsu · RLS · triggers', w=250, estereotipo='«base de datos»')
n4 = nodo(c, 400, 310, 220, 90, 'Gmail SMTP', '«servidor externo»')
n5 = nodo(c, 780, 310, 220, 90, 'Google OAuth', '«servidor externo»')
asociacion(c, n1, n2, etiqueta='HTTPS')
asociacion(c, n2, n3, etiqueta='TCP/TLS · PDO pdo_pgsql')
asociacion(c, n2, n4, etiqueta='SMTP/TLS (comprobantes)', tipo='dep')
asociacion(c, n2, n5, etiqueta='HTTPS (opcional)', tipo='dep')
c.guardar(ruta('despliegue'))

c = Lienzo()
capas = [('Capa de presentación', 'app/views (layouts, parciales, vistas por paquete) · public/assets (CSS, JS)'),
         ('Capa de control', 'api/index.php (Router) · app/controllers (un controlador por grupo de casos de uso)'),
         ('Capa de servicios y núcleo', 'app/core: Auth, Session, Csrf, Validator, View, Mailer · app/services: Pasarela'),
         ('Capa de modelo (acceso a datos)', 'app/models: un modelo por entidad, consultas con PDO y parámetros'),
         ('Capa de datos', 'PostgreSQL 17 · esquema mitsu: tablas, triggers, funciones PA1–PA12, RLS')]
for k, (t, d) in enumerate(capas):
    caja = paquete(c, 0, k * 110, 720, 80, t)
    c.texto(360, k * 110 + 58, d, 11.5, 'middle')
    if k:
        c.linea([(360, (k - 1) * 110 + 80), (360, k * 110)], dash='6,4', fin='abierta')
c.guardar(ruta('capas'))

c = Lienzo()
K = {}
K['nav'] = componente(c, 0, 0, 'Navegador', 'vistas HTML + app.js')
K['router'] = componente(c, 260, 0, 'Router', 'api/index.php')
K['nucleo'] = componente(c, 540, 0, 'Núcleo', 'Auth · Session · Csrf · View')
for k, (p, nombre) in enumerate(PAQUETES.items()):
    K[p] = componente(c, k * 250, 130, f'Controladores {p}', nombre.split(' ', 1)[1][:28], w=230)
    K['m' + p] = componente(c, k * 250, 250, f'Modelos {p}', '', w=230)
K['pasarela'] = componente(c, 1010, 130, 'Pasarela', 'tarjeta · QR · PayPal')
K['mailer'] = componente(c, 1010, 250, 'Mailer', 'SMTP')
K['bd'] = componente(c, 380, 370, 'PostgreSQL', 'esquema mitsu', estereotipo='«base de datos»')
asociacion(c, K['nav'], K['router'], tipo='dep', etiqueta='HTTP')
asociacion(c, K['router'], K['nucleo'], tipo='dep')
for p in PAQUETES:
    asociacion(c, K['router'], K[p], tipo='dep')
    asociacion(c, K[p], K['m' + p], tipo='dep')
    asociacion(c, K['m' + p], K['bd'], tipo='dep')
asociacion(c, K['P4'], K['pasarela'], tipo='dep')
asociacion(c, K['pasarela'], K['mailer'], tipo='dep')
c.guardar(ruta('componentes'))

for p, nombre in PAQUETES.items():
    lista = [cu for cu in CASOS if cu['paquete'] == p]
    frms, ctrls, ents = [], [], []
    for cu in lista:
        for k, t, n in cu['part']:
            dest = frms if t == 'frm' else ctrls if t == 'ctrl' else ents if t in ('ent', 'bd') else None
            if dest is not None and n not in dest:
                dest.append(n)
    usos = {}
    for cu in lista:
        t = {k: n for k, _, n in cu['part']}
        for k, tt, n in cu['part']:
            if tt in ('ent', 'bd'):
                usos.setdefault(n, []).append(ctrls.index(t['c']))
    frms.sort(key=lambda n: min(ctrls.index({k: m for k, _, m in cu['part']}['c']) for cu in lista if any(m == n for _, _, m in cu['part'])))
    ents.sort(key=lambda n: (n == 'Bitacora', sum(usos[n]) / len(usos[n])))
    c = Lienzo()
    cx = {}
    alto = max(len(frms), len(ctrls), len(ents)) * 70
    for col, (lst, est, x) in enumerate(((frms, '«vista»', 0), (ctrls, '«controlador»', 260), (ents, '«modelo»', 520))):
        y0 = (alto - len(lst) * 70) / 2
        for k, n in enumerate(lst):
            e = '«función SQL»' if n.startswith(('fn_', 'sp_')) else '«servicio»' if n == 'Pasarela' else est
            cx[n] = componente(c, x, y0 + k * 70, n, '', w=220, estereotipo=e)
    for cu in lista:
        t = {k: n for k, _, n in cu['part']}
        asociacion(c, cx[t['f']], cx[t['c']], tipo='dep')
        for k, tt, n in cu['part']:
            if tt in ('ent', 'bd') and n in cx:
                asociacion(c, cx[t['c']], cx[n], tipo='dep')
    c.guardar(ruta(f'componentes_{p}'))

# ---------------------------------------------------------------------------
# 8. Modelo de negocio: diagrama de actividad con carriles (situación actual)
# ---------------------------------------------------------------------------
CARRILES = ['Alumno / Cliente', 'Recepción (administrativa)', 'Maestra', 'Gerencia (administradora)']
NODOS = [  # (clave, carril, fila, tipo, texto)
    ('i', 0, 0, 'ini', ''),
    ('a1', 0, 1, 'acc', 'Consulta horarios por WhatsApp o en el local'),
    ('a2', 1, 1, 'acc', 'Revisa el cuaderno de horarios y cupos'),
    ('d1', 1, 2, 'dec', ''),
    ('a3', 1, 3, 'acc', 'Anota la reserva en el cuaderno'),
    ('a4', 0, 3, 'acc', 'Busca otro horario'),
    ('a5', 2, 4, 'acc', 'Dicta la clase y anota la asistencia en papel'),
    ('a6', 2, 5, 'acc', 'Avisa a recepción el material usado'),
    ('i2', 0, 5, 'ini', ''),
    ('a7', 0, 6, 'acc', 'Elige una pieza en el local o por redes'),
    ('a8', 1, 6, 'acc', 'Cobra, anota la venta y entrega la pieza'),
    ('a9', 3, 7, 'acc', 'Cuenta el dinero y revisa stock al cierre'),
    ('d2', 3, 8, 'dec', ''),
    ('a10', 3, 9, 'acc', 'Compra material al proveedor'),
    ('f', 3, 10, 'fin', ''),
]
ARISTAS = [('i', 'a1', ''), ('a1', 'a2', ''), ('a2', 'd1', ''), ('d1', 'a3', '[hay cupo]'), ('d1', 'a4', '[sin cupo]'), ('a4', 'a1', '', 'izq'), ('i2', 'a7', ''),
           ('a3', 'a5', ''), ('a5', 'a6', ''), ('a6', 'a8', ''), ('a7', 'a8', ''), ('a8', 'a9', ''), ('a9', 'd2', ''),
           ('d2', 'a10', '[stock bajo]'), ('d2', 'f', '[stock suficiente]', 'der'), ('a10', 'f', '')]
c = Lienzo()
ANCHO_C, ALTO_F = 250, 78
xs = [ANCHO_C * k + ANCHO_C / 2 for k in range(len(CARRILES))]
alto = (max(n[2] for n in NODOS) + 1) * ALTO_F + 50
for k, t in enumerate(CARRILES):
    c.rect(k * ANCHO_C, 0, ANCHO_C, alto, fill='white' if k % 2 else '#fdfaf5')
    c.rect(k * ANCHO_C, 0, ANCHO_C, 34, fill=RELLENO_2)
    c.texto(xs[k], 22, t, 12.5, 'middle', negrita=True)
geo = {}
for clave, carril, fila, tipo, texto in NODOS:
    x, y = xs[carril], 70 + fila * ALTO_F
    if tipo == 'acc':
        partes = uml._partir(texto, 180, 11.5)
        h = 18 + 14 * len(partes)
        geo[clave] = (x - 100, y - h / 2, 200, h)
    elif tipo == 'dec':
        geo[clave] = (x - 16, y - 16, 32, 32)
    else:
        geo[clave] = (x - 11, y - 11, 22, 22)
for a, b, g, *forma in ARISTAS:
    xa, ya, wa, ha = geo[a]
    xb, yb, wb, hb = geo[b]
    ca, cb = (xa + wa / 2, ya + ha / 2), (xb + wb / 2, yb + hb / 2)
    if forma == ['izq']:
        pts = [(xa, ca[1]), (xa - 22, ca[1]), (xb - 22, cb[1]), (xb, cb[1])]
    elif forma == ['der']:
        xr = cb[0] + 112
        pts = [(xa + wa, ca[1]), (xr, ca[1]), (xr, cb[1]), (xb + wb, cb[1])]
    elif abs(ca[0] - cb[0]) < 1:
        if cb[1] > ca[1]:
            pts = [(ca[0], ya + ha), (cb[0], yb)]
        else:
            pts = [(xa + wa, ca[1]), (xa + wa + 18, ca[1]), (xa + wa + 18, cb[1]), (xb + wb, cb[1])]
    elif abs(ca[1] - cb[1]) < 1:
        pts = [(xa + wa if cb[0] > ca[0] else xa, ca[1]), (xb if cb[0] > ca[0] else xb + wb, cb[1])]
    else:
        if cb[1] < ca[1]:
            pts = [(xa, ca[1]), (cb[0], ca[1]), (cb[0], yb + hb)]
        else:
            pts = [(xa + wa if cb[0] > ca[0] else xa, ca[1]), (cb[0], ca[1]), (cb[0], yb)]
    c.linea(pts, fin='llena')
    if g and forma == ['der']:
        c.texto(pts[0][0] + 4, pts[0][1] - 6, g, 10.5, cursiva=True, color=ACENTO, fondo='white')
    elif g:
        c.texto((pts[0][0] + pts[1][0]) / 2, pts[0][1] - 6 if abs(pts[0][1] - pts[1][1]) < 1 else (pts[0][1] + pts[1][1]) / 2,
                g, 10.5, 'middle', cursiva=True, color=ACENTO, fondo='white')
for clave, carril, fila, tipo, texto in NODOS:
    x, y, w, h = geo[clave]
    if tipo == 'acc':
        c.rect(x, y, w, h, rx=12, fill=RELLENO)
        partes = uml._partir(texto, 180, 11.5)
        for j, t in enumerate(partes):
            c.texto(x + w / 2, y + 22 + 14 * j, t, 11.5, 'middle')
    elif tipo == 'dec':
        c.poligono([(x + 16, y), (x + 32, y + 16), (x + 16, y + 32), (x, y + 16)], fill=RELLENO_2)
    elif tipo == 'ini':
        c.elipse(x + 11, y + 11, 11, 11, fill=TINTA)
    else:
        c.elipse(x + 11, y + 11, 11, 11, fill='white')
        c.elipse(x + 11, y + 11, 6, 6, fill=TINTA)
c.guardar(ruta('actividad_negocio'))

# ---------------------------------------------------------------------------
# Datos de los casos de uso para el informe
# ---------------------------------------------------------------------------
salida = [{k: v for k, v in cu.items() if k not in ('part', 'pasos')} | {
    'participantes': [{'tipo': t, 'nombre': n} for _, t, n in cu['part']]} for cu in CASOS]
json.dump({'casos': salida, 'actores': ACTORES, 'paquetes': PAQUETES}, open(os.path.join(DATOS, 'casos.json'), 'w', encoding='utf8'),
          ensure_ascii=False, indent=1)
print(len(os.listdir(SVG)), 'diagramas SVG')
