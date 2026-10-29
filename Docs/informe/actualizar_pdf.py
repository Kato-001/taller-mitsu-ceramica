"""
Abre el informe en LibreOffice (sin ventana), actualiza el índice, lo vuelve a guardar
como .docx y lo exporta a PDF.

    "C:\\Program Files\\LibreOffice\\program\\python.exe" Docs/informe/actualizar_pdf.py
"""

import os
import subprocess
import sys
import time

import uno
from com.sun.star.beans import PropertyValue

AQUI = os.path.dirname(os.path.abspath(__file__))
DOCX = os.path.join(AQUI, 'Informe_Taller_Mitsu_Ciclos_I-III.docx')
PDF = DOCX.replace('.docx', '.pdf')
SOFFICE = r'C:\Program Files\LibreOffice\program\soffice.exe'


def prop(nombre, valor):
    p = PropertyValue()
    p.Name, p.Value = nombre, valor
    return p


def url(ruta):
    return uno.systemPathToFileUrl(os.path.abspath(ruta))


proceso = subprocess.Popen([SOFFICE, '--headless', '--invisible', '--nologo', '--norestore',
                            '--accept=socket,host=127.0.0.1,port=2002;urp;'])
contexto = uno.getComponentContext()
resolver = contexto.ServiceManager.createInstanceWithContext('com.sun.star.bridge.UnoUrlResolver', contexto)
for _ in range(60):
    try:
        remoto = resolver.resolve('uno:socket,host=127.0.0.1,port=2002;urp;StarOffice.ComponentContext')
        break
    except Exception:
        time.sleep(1)
else:
    sys.exit('No se pudo conectar con LibreOffice')

escritorio = remoto.ServiceManager.createInstanceWithContext('com.sun.star.frame.Desktop', remoto)
doc = escritorio.loadComponentFromURL(url(DOCX), '_blank', 0, (prop('Hidden', True),))
try:
    # dos pasadas: la primera crea las entradas y la segunda corrige los números de página
    for _ in range(2):
        indices = doc.getDocumentIndexes()
        for i in range(indices.getCount()):
            indices.getByIndex(i).update()
        doc.refresh()
    doc.storeToURL(url(DOCX), (prop('FilterName', 'MS Word 2007 XML'),))
    doc.storeToURL(url(PDF), (prop('FilterName', 'writer_pdf_Export'),))
    print('Índice actualizado y PDF exportado:', os.path.basename(PDF))
finally:
    doc.close(True)
    try:
        escritorio.terminate()
    except Exception:
        pass
    proceso.wait(timeout=60)
