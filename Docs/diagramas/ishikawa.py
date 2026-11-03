"""
Método de Ishikawa del Taller Mitsü, paso a paso:

    arbol_problemas.png       1.5  Análisis de problemas (lista inicial agrupada en la lista final)
    ishikawa_eje.png          1.9  Diseñar el diagrama: eje y efecto
    ishikawa_categorias.png   2.   Identificar las principales categorías
    ishikawa.png              3.   Identificar las causas (diagrama completo)

Los problemas salen de la lista inicial y de la depuración del capítulo 1 (entrevista a la gerente).

    python Docs/diagramas/ishikawa.py
"""

import os
import textwrap
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt

plt.rcParams['font.family'] = 'Arial'
TINTA, ARCILLA, CREMA, GRIS = '#2b2b2b', '#9c5b3a', '#f1e4d3', '#8a8a8a'
SALIDA = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'png')
FUENTE = 'Fuente: elaboración propia a partir de la entrevista a la gerente y de la lista final de problemas.'

EFECTO = 'Gestión manual y\ndesintegrada de clases,\ninventario y ventas\ndel Taller Mitsü'
ARRIBA = [
    ('C1. Métodos y procesos', ['Horarios y reservas en hojas de Excel', 'Ventas anotadas a mano, sin nota numerada',
                                'Sin cierre de caja ni arqueo diario']),
    ('C2. Personas', ['Personal reducido (no se puede contratar más)', 'Maestras asignadas sin registro central',
                      'Recepción responde reservas una por una']),
    ('C3. Tecnología', ['Excel y software de finanzas no integrados', 'Sin página para ver piezas o clases',
                        'Sin respaldos ni control de acceso']),
]
ABAJO = [
    ('C4. Materiales e inventario', ['Stock de arcilla y minerales sin registro', 'Compras urgentes por falta de material',
                                     'Solo dos proveedores (Cbba. y La Paz)']),
    ('C5. Medición y control', ['Sin historial de asistencia de alumnos', 'Sin reportes de ventas ni indicadores',
                                'Precios y costos sin historial']),
    ('C6. Clientes y entorno', ['Alumnos no ven cupos en tiempo real', 'Clientes no pueden comprar ni pagar en línea',
                                'Competencia de otros talleres de la ciudad']),
]

# Lista inicial de problemas (1.1) y su agrupación en la lista final (1.2 y 1.3)
PROBLEMAS = {
    1: 'Horarios de clases en hojas de Excel', 2: 'Alumnos sin consulta de disponibilidad en tiempo real',
    3: 'Confusión al asignar maestras', 4: 'Inventario de materiales sin registro', 5: 'Falta de materiales en momentos críticos',
    6: 'Compras urgentes sin planificación', 7: 'Verificación manual de existencias', 8: 'Ventas de piezas sin registro centralizado',
    9: 'Cafetería no integrada', 10: 'Dificultad para generar reportes de ventas', 11: 'Falta de indicadores para decidir',
    12: 'Descoordinación entre áreas', 13: 'Retrasos en la atención a clientes', 14: 'Herramientas externas no integradas',
    15: 'Demanda de clases difícil de organizar', 16: 'Sin historial de asistencia', 17: 'Sin control de pagos y morosidad',
    18: 'Información de clientes dispersa', 19: 'Sin registro de pedidos personalizados', 20: 'Duplicidad y errores de registro',
    21: 'Riesgo de pérdida de datos',
}
FINAL = [
    ('P1. Horarios y reservas manuales', [1, 2, 3, 15]), ('P2. Sin historial de asistencia', [16]),
    ('P3. Inventario sin registro', [4, 5, 6, 7]), ('P4. Ventas sin registro ni caja', [8, 10, 11]),
    ('P5. Información dispersa', [12, 13, 14, 18, 20]), ('P6. Sin venta en línea', []), ('P7. Riesgo de pérdida de datos', [21]),
    ('Postergados al ciclo IV', [9, 17, 19]),
]


def guardar(nombre):
    plt.savefig(os.path.join(SALIDA, nombre), dpi=200, bbox_inches='tight', facecolor='white')
    plt.close()
    print(nombre)


def arbol_problemas():
    """Árbol: cada problema inicial apunta al problema final que lo agrupa, y estos al problema central."""
    fig, ax = plt.subplots(figsize=(18, 9.4))
    ax.set_xlim(0, 18)
    ax.set_ylim(0, 9.4)
    ax.axis('off')
    caja = lambda x, y, t, fc, ec, color, tam, peso='normal': ax.text(
        x, y, t, ha='center', va='center', fontsize=tam, fontweight=peso, color=color,
        bbox=dict(boxstyle='round,pad=0.45', fc=fc, ec=ec, lw=1.2))
    flecha = lambda x1, y1, x2, y2: ax.annotate('', xy=(x2, y2), xytext=(x1, y1),
                                                arrowprops=dict(arrowstyle='-|>', lw=1, color=GRIS, mutation_scale=10))
    caja(9, 8.7, 'Análisis del problema:\ngestión manual y desintegrada del taller', ARCILLA, ARCILLA, 'white', 12.5, 'bold')
    ax.plot([1.1, 16.9], [7.75, 7.75], color=GRIS, lw=1)
    flecha(9, 7.75, 9, 8.2)
    for k, (titulo, hijos) in enumerate(FINAL):
        x = 1.1 + k * (15.8 / 7)
        postergado = not titulo.startswith('P')
        caja(x, 7.0, textwrap.fill(titulo, 16), '#eeeeee' if postergado else CREMA, GRIS if postergado else ARCILLA,
             GRIS if postergado else TINTA, 9.5, 'bold')
        ax.plot([x, x], [7.4, 7.75], color=GRIS, lw=1)
        if not hijos:
            ax.text(x, 5.9, 'No agrupa problemas de la\nlista inicial: se agregó\nen la lista final (1.3)', ha='center', va='center', fontsize=7.8,
                    style='italic', color=GRIS)
        for j, n in enumerate(hijos):
            y = 5.85 - j * 1.12
            caja(x, y, textwrap.fill(f'{n}. {PROBLEMAS[n]}', 19), 'white', GRIS, GRIS if postergado else TINTA, 8)
            flecha(x, y + 0.42, x, (6.6 if j == 0 else y + 0.7))
    ax.text(0.3, 0.05, 'Fuente: elaboración propia a partir de la lista inicial de problemas (1.1) y su depuración (1.2).',
            fontsize=9, style='italic', color='#666')
    guardar('arbol_problemas.png')


def pescado(categorias, causas, nombre):
    """Espina de pescado: solo eje y efecto, con las categorías, o completa con las causas."""
    fig, ax = plt.subplots(figsize=(16, 8.6 if categorias else 4.2))
    alto = 8.6 if categorias else 4.2
    ax.set_xlim(0, 16)
    ax.set_ylim(0, alto)
    ax.axis('off')
    y0 = alto / 2
    ax.annotate('', xy=(12.2, y0), xytext=(0.4, y0), arrowprops=dict(arrowstyle='-|>', lw=2.6, color=TINTA, mutation_scale=22))
    ax.text(14.05, y0, EFECTO, ha='center', va='center', fontsize=12.5, fontweight='bold', color='white',
            bbox=dict(boxstyle='round,pad=0.6', fc=ARCILLA, ec=ARCILLA))
    if categorias:
        for grupos, signo in ((ARRIBA, 1), (ABAJO, -1)):
            for k, (categoria, lista) in enumerate(grupos):
                xb = 2.2 + k * 3.6           # donde la espina toca el eje
                xt = xb - 1.9                # extremo de la espina
                yt = y0 + signo * 3.4
                ax.plot([xt, xb], [yt, y0], color=TINTA, lw=1.8)
                ax.text(xt, yt + signo * 0.32, categoria, ha='center', va='center', fontsize=12, fontweight='bold',
                        bbox=dict(boxstyle='round,pad=0.35', fc=CREMA, ec=ARCILLA))
                for j, causa in enumerate(lista if causas else []):
                    t = 0.25 + j * 0.27
                    x, y = xt + (xb - xt) * t, yt + (y0 - yt) * t
                    ax.plot([x, x + 1.55], [y, y], color=ARCILLA, lw=1.1)
                    ax.text(x + 0.08, y + 0.07, causa, fontsize=8.6, va='bottom', color=TINTA, wrap=True)
    ax.text(0.4, 0.15, FUENTE, fontsize=9, style='italic', color='#666')
    guardar(nombre)


arbol_problemas()
pescado(False, False, 'ishikawa_eje.png')
pescado(True, False, 'ishikawa_categorias.png')
pescado(True, True, 'ishikawa.png')
