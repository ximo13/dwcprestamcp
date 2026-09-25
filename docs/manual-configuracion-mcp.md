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

Si ves esos datos, el endpoint, el token y las herramientas están operativos.

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

| Categoría | Herramienta | Tipo | Qué hace |
|---|---|---|---|
| Tienda | `dwc_get_store_info` | solo lectura | Datos básicos de la tienda: nombre, versión de PrestaShop/PHP, idioma y moneda por defecto e idiomas activos. |
| Productos | `dwc_get_low_stock_products` | solo lectura | Productos con stock igual o por debajo de un umbral. |
| Productos | `dwc_search_products` | solo lectura | Busca productos por nombre o referencia; precio, stock y si está activo. |
| Productos | `dwc_get_product_stock` | solo lectura | Stock de un producto, desglosado por combinación (talla, color…). |
| Productos | `dwc_get_product_details` | solo lectura | Ficha completa: categorías, marca, proveedor, precios con y sin IVA, EAN, imágenes, combinaciones y características. |
| Productos | `dwc_get_low_stock_combinations` | solo lectura | Combinaciones de productos activos con stock igual o por debajo de un umbral. |
| Productos | `dwc_get_unsold_products` | solo lectura | Productos activos con stock y sin ventas en los últimos N días (stock parado). |
| Productos | `dwc_get_unavailable_products` | solo lectura | Productos desactivados, o activos con stock 0 (e indica si admiten pedidos sin stock). |
| Productos | `dwc_get_products_with_catalog_issues` | solo lectura | Productos sin imagen, sin categoría (solo Inicio o ninguna) o sin EAN. |
| Productos | `dwc_get_product_discounts` | solo lectura | Descuentos (precios específicos): reducción, fechas y restricciones; vigentes, programados o todos. |
| Productos | `dwc_list_categories` | solo lectura | Categorías (o las que contengan un texto) con su categoría padre y número de productos. |
| Productos | `dwc_list_brands` | solo lectura | Marcas (fabricantes) con su número de productos. |
| Productos | `dwc_list_features` | solo lectura | Características con sus valores predefinidos e IDs. |
| Productos | `dwc_get_products_missing_content` | solo lectura | Productos con la descripción corta/larga o el meta título/descripción vacíos o demasiado cortos. |
| Productos | `dwc_get_product_content` | solo lectura | Descripciones y meta SEO de un producto, con su longitud, en el idioma por defecto o en otro. |
| Productos | `dwc_update_product` | **escritura** | Actualiza precio, precio de coste, regla de IVA, activo, nombre, referencia, EAN/UPC/ISBN/MPN, peso y medidas, visibilidad, disponible para pedido, mostrar precio, cantidad mínima, estado, comportamiento sin stock, URL amigable, textos de disponibilidad, `on_sale` y stock. Solo cambia los campos enviados; nombre, URL y textos de disponibilidad, en el idioma por defecto o en otro (`language`, código ISO). |
| Productos | `dwc_update_combination_stock` | **escritura** | Cambia el stock de una combinación (talla, color…); el total del producto se recalcula. |
| Productos | `dwc_bulk_update_prices` | **escritura** | Sube o baja precios de una categoría y/o marca en % o importe. Primero devuelve una vista previa; solo aplica con `confirm=true`. |
| Productos | `dwc_create_product_discount` | **escritura** | Crea un descuento (porcentaje o importe), opcionalmente entre dos fechas. |
| Productos | `dwc_delete_product_discount` | **escritura** | Elimina un descuento (no los de reglas de precios del catálogo). |
| Productos | `dwc_update_product_categories` | **escritura** | Añade o quita categorías de un producto y cambia su categoría principal. |
| Productos | `dwc_set_product_brand` | **escritura** | Asigna o quita la marca de un producto. |
| Productos | `dwc_update_product_features` | **escritura** | Asigna (valor predefinido o texto personalizado) o quita características de un producto; el resto se conserva. |
| Productos | `dwc_update_product_description` | **escritura** | Actualiza la descripción corta y/o larga, en el idioma por defecto o en otro (`language`, código ISO). |
| Productos | `dwc_update_product_meta` | **escritura** | Actualiza el meta título y/o la meta descripción, en el idioma por defecto o en otro (`language`, código ISO). |
| Pedidos y ventas | `dwc_get_orders_by_status` | solo lectura | Pedidos recientes, opcionalmente filtrados por estado. |
| Pedidos y ventas | `dwc_get_sales_by_date_range` | solo lectura | Facturación, número de pedidos y ticket medio de un periodo. |
| Pedidos y ventas | `dwc_get_top_selling_products` | solo lectura | Productos más vendidos (por unidades) en un periodo. |
| Pedidos y ventas | `dwc_get_abandoned_carts` | solo lectura | Carritos con productos que no llegaron a pedido, en los últimos N días. |
| Clientes | `dwc_get_customers` | solo lectura | Busca clientes por email o lista los más recientes. |

Las herramientas de **escritura** modifican datos reales de la tienda; el asistente debe pedir confirmación antes de usarlas.

> El uso de cada herramienta, con frases de ejemplo, se explica en la pestaña
> **Manual** de la página de configuración del módulo.

---

*Documento generado para el módulo `dwcprestamcp`. Mantener sincronizado con el
código (`dwcprestamcp.php`, `src/Http/McpHttpHandler.php`, `src/Tools/`).*
