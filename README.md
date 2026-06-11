# ID Rotador de Banners

Plugin de WordPress ligero para el Instituto Duartiano: banners que rotan automáticamente, con enlace opcional por banner (interno o externo) e importador de los sliders antiguos de Slider Revolution.

## Instalación

1. Comprime la carpeta `id-rotador-banners` en un archivo `.zip`.
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin**, sube el zip y actívalo.
3. Aparecerá el menú **Banners** en el panel lateral.

## Importar tus sliders antiguos de Slider Revolution

1. Ve a **Banners → Importar de Slider Revolution**.
2. Verás la lista de sliders existentes (lee las tablas `wp_revslider_*` directamente, así que funciona aunque Slider Revolution esté desactivado, siempre que no se hayan borrado sus datos).
3. Pulsa **Importar** en el slider que quieras. Cada diapositiva se convierte en un banner con su imagen de fondo y su enlace (si lo tenía), agrupados bajo el nombre del slider.

**Limitación:** solo se importa la imagen de fondo y el enlace de cada diapositiva. Las capas de texto, botones y animaciones de Slider Revolution no se migran (revisa cada banner importado y ajusta el enlace si hace falta).

## Crear banners manualmente

1. **Banners → Añadir nuevo banner**.
2. Ponle un título (solo se usa internamente y como texto accesible).
3. Asigna la **Imagen destacada** (esa es la imagen del banner).
4. En **Enlace del banner**, pega la URL de destino si quieres que sea clicable; marca "pestaña nueva" para enlaces externos.
5. Opcional: asígnalo a un **Grupo** para tener varios rotadores distintos (portada, sección de prensa, etc.).
6. El orden se controla con el campo **Orden** (Atributos de página).

## Mostrar el rotador

Inserta el shortcode en cualquier página, entrada o widget:

```
[id_banners]
```

Con opciones:

```
[id_banners grupo="portada" alto="450" intervalo="5000" flechas="1" puntos="1"]
```

| Atributo | Qué hace | Por defecto |
|---|---|---|
| `grupo` | Slug del grupo de banners a mostrar (vacío = todos) | todos |
| `alto` | Alto del rotador en píxeles | 450 |
| `intervalo` | Milisegundos entre cambios de banner | 5000 |
| `flechas` | Mostrar flechas anterior/siguiente (1 o 0) | 1 |
| `puntos` | Mostrar puntos de navegación (1 o 0) | 1 |

En Avada también puedes insertarlo con el elemento "Code Block" o en cualquier zona de widgets.

## Actualizaciones automáticas desde GitHub

El plugin comprueba cada 6 horas la versión publicada en la rama `main` de
`github.com/FreddyAquinoPortes/id-rotador-banners`. Si la cabecera `Version:` del
archivo `id-rotador-banners.php` del repo es mayor que la instalada, WordPress
muestra el botón **Actualizar** estándar en la página de Plugins.

Flujo para publicar una nueva versión:

1. Edita el código y sube el número en **dos sitios** de `id-rotador-banners.php`:
   la cabecera `* Version: x.y.z` y la constante `IDRB_VERSION`.
2. Haz commit y push a la rama `main`.
3. En los sitios WordPress, el aviso de actualización aparece solo (máximo 6 horas
   después), o al instante entrando en **Escritorio → Actualizaciones → Comprobar de nuevo**.

## Notas técnicas

- Sin dependencias: no usa jQuery ni librerías externas; CSS y JS se imprimen solo en páginas que usan el shortcode.
- Las imágenes importadas se reutilizan de la mediateca si ya existen; si no, se descargan a ella.
- Requiere PHP 7.4+ y WordPress 5.8+.
