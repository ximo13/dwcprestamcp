# Manual de configuración del MCP (`dwcprestamcp`)

Este manual explica cómo instalar, configurar y conectar el servidor MCP que
incluye el módulo **DWC PrestaShop MCP** (`dwcprestamcp`), para poder usar la
tienda desde un cliente de IA (Claude, ChatGPT, Gemini, MCP Inspector).

> Es un servidor MCP **autónomo**: se ejecuta dentro de PrestaShop, expone su
> propio endpoint HTTP autenticado por token y **no** depende de
> `ps_mcp_server`, `ps_accounts` ni `ps_eventbus`.

Módulo: `dwcprestamcp` · Versión: **2.3.0** · Autor: DWC · Licencia: MIT

---

## 1. Requisitos

| Requisito | Valor |
|---|---|
| PrestaShop | **8.2+** o **9.x** |
| PHP | **8.1+** |
| Composer | Necesario para instalar el SDK de MCP en `vendor/` (o usar un ZIP que ya lo incluya) |
| HTTPS | Obligatorio en producción (el token viaja en cada petición) |

---

## 2. Instalación

### 2.1. Colocar el módulo y sus dependencias

Desde la raíz de la tienda:

```bash
git clone https://github.com/ximo13/dwcprestamcp.git modules/dwcprestamcp
cd modules/dwcprestamcp
composer install --no-dev   # instala el SDK de MCP dentro de vendor/
```

> **ZIP para comercios (sin Composer):** si prefieres instalar por
> *Módulos → Subir un módulo*, usa un paquete de release que ya incluya la
> carpeta `vendor/` (ver `CONTRIBUTING.md`). Sin `vendor/autoload.php` el
> endpoint devuelve un error 500 pidiendo ejecutar `composer install`.

### 2.2. Instalar el módulo en PrestaShop

- **Back office:** *Módulos → Gestor de módulos*, busca "DWC PrestaShop MCP" e
  instálalo.
- **CLI (opcional):**

  ```bash
  php bin/console prestashop:module install dwcprestamcp
  ```

Al instalarse, el módulo **genera automáticamente un token de acceso** aleatorio
(32 bytes → 64 caracteres hex) y lo guarda en la clave de configuración
`DWCPRESTAMCP_TOKEN`.

---

## 3. Página de configuración

Abre la configuración del módulo (*Gestor de módulos → DWC PrestaShop MCP →
Configurar*). Verás tres campos, todos de solo lectura y pensados para copiar:

1. **MCP endpoint URL** — la URL que debes dar al cliente de IA:

   ```
   https://TU-TIENDA.tld/modules/dwcprestamcp/mcp.php
   ```

   Es el **archivo físico `mcp.php`**, que trae su propio `.htaccess` (ver
   §6). Es el endpoint recomendado.

2. **Access token (Bearer)** — el token secreto. Cualquiera que lo tenga puede
   llamar a las herramientas de tu tienda. **No lo compartas.**

3. **Client configuration (example)** — un JSON listo para pegar en tu cliente
   MCP, ya con tu endpoint y tu token:

   ```json
   {
       "mcpServers": {
           "prestashop-dwc": {
               "url": "https://TU-TIENDA.tld/modules/dwcprestamcp/mcp.php",
               "headers": {
                   "Authorization": "Bearer TU_TOKEN"
               }
           }
       }
   }
   ```

### Regenerar el token

En la misma página, el botón **Regenerar token** crea uno nuevo. **El token
anterior deja de funcionar de inmediato**, así que después tendrás que
actualizar la configuración en todos los clientes conectados. Úsalo si el token
se ha filtrado o de forma periódica por higiene de seguridad.

---

## 4. Conectar un cliente de IA

### 4.1. Remoto (HTTP) — Claude, ChatGPT, etc.

Usa el snippet JSON de la página de configuración (§3). El cliente enviará en
cada petición la cabecera:

```
Authorization: Bearer TU_TOKEN
```

### 4.2. Local (STDIO) — Claude Desktop en la misma máquina

Si el cliente corre en el **mismo servidor** que la tienda, puedes lanzar el
servidor por STDIO sin exponer HTTP:

```json
{
    "mcpServers": {
        "prestashop-dwc-local": {
            "command": "php",
            "args": ["/ruta/absoluta/modules/dwcprestamcp/bin/mcp-stdio.php"]
        }
    }
}
```

El script `bin/mcp-stdio.php` arranca PrestaShop (lee `app/config/parameters.php`
y `config/config.inc.php`) para que las herramientas tengan contexto real de la
tienda, y sirve el protocolo MCP por entrada/salida estándar.

---

## 5. Verificar que funciona

1. Guarda la configuración en tu cliente MCP.
2. Reinicia/reconecta el cliente para que descubra las herramientas.
3. Ejecuta la herramienta de solo lectura **`dwc_get_store_info`**. Debe
   devolver algo como:

   ```json
   {
       "shop_name": "My Shop",
       "prestashop_version": "9.x.x",
       "php_version": "8.x.x",
       "default_language": "xx",
       "default_currency": "XXX"
   }
   ```

Si ves esos datos, el endpoint, el token y las tres herramientas están
operativos.

---

## 6. Seguridad

- **Token Bearer.** Toda petición debe llevar `Authorization: Bearer <token>`.
  El módulo compara con `hash_equals()` (comparación en tiempo constante). Sin
  token válido responde **401 Unauthorized**.
- **HTTPS siempre en producción.** El token viaja en claro dentro de la
  cabecera.
- **Protección anti DNS-rebinding.** El endpoint solo acepta peticiones cuyo
  `Host` esté en la lista blanca, construida a partir de:
  `localhost`, `127.0.0.1`, `[::1]`, y los dominios de la tienda
  (`PS_SHOP_DOMAIN`, `PS_SHOP_DOMAIN_SSL` y el host actual). Si accedes por un
  dominio no configurado en PrestaShop, la petición se rechaza.
- **CORS** activo mediante middleware.
- **`.htaccess` propio del módulo.** Bloquea por defecto todos los `.php` de la
  carpeta y **solo** deja accesible `mcp.php`. Además exime a `mcp.php` de las
  reglas de ModSecurity/WAF que en algunos hostings (LiteSpeed/OVH) bloquean los
  POST sin cookie ni referer. Como solo `mcp.php` es accesible, la seguridad
  global de la tienda no se ve afectada, y el endpoint sigue exigiendo token.

---

## 7. Resolución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| **401 Unauthorized** | Token ausente, mal copiado o regenerado | Copia de nuevo el token desde la página de configuración; revisa la cabecera `Authorization: Bearer ...` |
| **Error 500 "MCP dependencies are not installed"** | Falta `vendor/autoload.php` | Ejecuta `composer install --no-dev` dentro de `modules/dwcprestamcp`, o instala el ZIP con `vendor/` incluido |
| **El endpoint no responde / lo bloquea el WAF** | Regla ModSecurity/WAF del hosting | Usa el endpoint físico `.../mcp.php` (no la URL amigable): trae el `.htaccess` que lo exime del WAF |
| **Host rechazado** | Accedes por un dominio no listado | Añade el dominio en *Parámetros de la tienda → Tráfico y SEO* (o accede por el dominio principal de la tienda) |
| **Host duplicado (`shop.tld, shop.tld`)** | Proxy/SAPI que duplica la cabecera `Host` | El handler ya la normaliza automáticamente; si persiste, revisa el proxy inverso |

> **Endpoint físico vs. URL amigable.** El módulo también responde en
> `/module/dwcprestamcp/mcp` (front controller), pero el recomendado es el
> archivo físico `/modules/dwcprestamcp/mcp.php` por el `.htaccess` propio.

---

## 8. Herramientas incluidas

| Herramienta | Tipo | Qué hace |
|---|---|---|
| `dwc_get_store_info` | solo lectura | Datos básicos de la tienda: nombre, versión de PrestaShop/PHP, idioma y moneda por defecto. |
| `dwc_get_low_stock_products` | solo lectura | Productos con stock igual o por debajo de un umbral (parámetros: `threshold`, `limit`). |
| `dwc_update_product` | **escritura** | Actualiza un producto existente: precio, activo, nombre, referencia, peso, indicador de oferta (`on_sale`) y cantidad de stock. Solo cambia los campos que envíes. Modifica datos reales de la tienda. |

> El detalle de parámetros y el uso de cada herramienta (incluidos los matices
> de `on_sale` y del stock) se cubren en el **manual de uso de las
> herramientas**, documento aparte.

---

*Documento generado para el módulo `dwcprestamcp`. Mantener sincronizado con el
código (`dwcprestamcp.php`, `src/Http/McpHttpHandler.php`, `src/Tools/`).*
