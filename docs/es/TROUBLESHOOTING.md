> Traducción al español. Versión original en inglés: [../TROUBLESHOOTING.md](../TROUBLESHOOTING.md).

# Solución de problemas

## La publicación falla con un error de autenticación

Síntomas en el registro de ejecución de GitHub Actions, p. ej.:

```
WordPressError: GET .../wp-json/wp/v2/categories -> 400:
{"status":"error","error":"INVALID_PASSWORD","code":"400","error_description":"Incorrect password."}
```

Revisa estos puntos en orden.

### 1. Username / Application Password

- `WP_USERNAME` debe ser el **nombre de usuario de inicio de sesión** del usuario (Users → Profile →
  "Username"), no su nombre para mostrar ni su correo electrónico.
- `WP_APP_PASSWORD` debe ser un **Application Password** de WordPress (Users →
  Profile → Application Passwords), no la contraseña normal de inicio de sesión. Genera una
  nueva y pégala exactamente (los espacios están bien).
- Verificación rápida: Users → Profile → Application Passwords → la columna **"Last used"**.
  Si permanece **vacía** después de una ejecución, la solicitud nunca llegó al
  núcleo de WordPress — algo la está interceptando (ver más abajo). Si muestra una
  hora reciente, el valor en sí es incorrecto.

### 2. Un plugin de seguridad / firewall está interceptando la REST API

Un cuerpo de error no estándar (con `status` / `error` / `error_description`)
generalmente significa que un plugin o el firewall del host está manejando la autenticación antes que WordPress.

**Este sitio (galapagosislands.travel) ejecuta el plugin "REST API
Authentication" de miniOrange.** De forma predeterminada protege cada endpoint `/wp-json` y
**no acepta Application Passwords de WordPress** — lo que bloquea la publicación.

**La solución (ya aplicada aquí):**

1. En el admin de WordPress, abre **miniOrange → Protected REST APIs**.
2. En **Protected WordPress Default REST APIs → WordPress**, **desmarca**
   `/wp/v2` (esto limpia todos los endpoints predeterminados debajo). Como mínimo,
   desmarca: `/wp/v2/posts`, `/wp/v2/pages`, `/wp/v2/categories`,
   `/wp/v2/tags`, `/wp/v2/media`.
3. Haz clic en **Save**.

Desmarcar estos devuelve esos endpoints a las reglas normales de WordPress.
Crear/editar contenido aún requiere autenticación (tu Application
Password) — solo estás quitando la barrera *extra* de miniOrange. Que las lecturas de
contenido ya público sean públicas es el comportamiento estándar de WordPress.

> WP Cerber Security **también** está instalado pero **no** fue la causa — desactivarlo
> no cambió el error. Déjalo activo.

### 3. Apache elimina el encabezado Authorization (otros hosts)

En algunos hosts Apache el encabezado `Authorization` se elimina antes de que WordPress
lo vea, por lo que los Application Passwords siempre fallan. Si te topas con esto en otro sitio,
agrega a `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
```

## Cómo leer una ejecución fallida

GitHub → **Actions** → haz clic en la ejecución fallida → haz clic en el job **run** → expande
el paso **Run**. El error real (la línea `WordPressError: ...`) está cerca del
final, justo encima de la salida de limpieza.

## Otras notas

- La publicación es **idempotente respecto al slug** — volver a ejecutar el mismo documento
  actualiza la entrada existente en lugar de crear un duplicado.
- El modo `preview` nunca toca WordPress; úsalo para verificar la coherencia de un documento.
- Si alguna vez expones una contraseña real (p. ej. en una captura de pantalla/prueba), rótala.
